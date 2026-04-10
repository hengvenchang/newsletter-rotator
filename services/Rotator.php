<?php
/**
 * Newsletter Subscribers Rotator Service
 *
 * This class implements the core rotation algorithm for distributing newsletter
 * emails across different email providers to prevent blacklisting. It uses
 * domain-based interleaving with hourly rate limiting to ensure safe sending.
 *
 * @author Newsletter Rotator Team
 * @version 1.0
 */

require_once __DIR__ . '/../helpers/helpers.php';

/**
 * Rotator class handles subscriber batching with domain rotation and rate limiting
 *
 * The rotation algorithm:
 * 1. Groups subscribers by email provider
 * 2. Enforces 100 emails per hour per provider limit
 * 3. Uses round-robin to interleave remaining providers
 * 4. Skips providers that hit hourly limit
 * 5. Prevents blacklisting through balanced distribution
 */
class Rotator {
    /** @var mysqli Database connection */
    private $conn;
    
    /** @var int Hourly limit per provider (100 emails/hour is safe) */
    private $hourlyLimit = 100;
    
    /** @var int Current hour (in second increments for grouping) */
    private $currentHour;

    /**
     * Constructor - establishes database connection and initializes hour tracking
     */
    public function __construct() {
        try {
            $this->conn = getDBConnection();
        } catch (Exception $e) {
            writeLog('DB connection failed: ' . $e->getMessage(), 'ERROR');
            throw $e;
        }
        $this->currentHour = floor(time() / 3600); // Group by hour
    }

    /**
     * Gets the hourly limit per provider
     * 
     * @return int The maximum emails allowed per provider per hour
     */
    public function getHourlyLimit() {
        return $this->hourlyLimit;
    }

    /**
     * Sets a custom hourly limit (useful for testing or different providers)
     *
     * @param int $limit The new hourly limit
     */
    public function setHourlyLimit($limit) {
        $this->hourlyLimit = $limit;
    }

    /**
     * Counts how many emails have been sent to a specific provider this hour
     *
     * @param string $domain The normalized domain to check
     * @return int Number of emails sent to this domain in the current hour
     */
    private function getProviderHourlyCount($domain) {
        try {
            $sql = "SELECT COUNT(*) as count FROM sent_emails se
                    JOIN subscribers s ON se.subscriber_id = s.id
                    WHERE DATE_ADD(se.sent_at, INTERVAL 1 HOUR) > NOW()
                    AND LOWER(s.email) LIKE ?";
            $stmt = $this->conn->prepare($sql);
            $pattern = '%@' . $domain . '%';
            $stmt->bind_param("s", $pattern);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return (int)$row['count'];
        } catch (Exception $e) {
            writeLog('Error in getProviderHourlyCount: ' . $e->getMessage(), 'ERROR');
            return 0;
        }
    }

    /**
     * Gets hourly counts for all providers in one query
     *
     * @return array Associative array of domain => count
     */
    private function getAllProviderCounts() {
        $counts = [];
        try {
            $sql = "SELECT LOWER(SUBSTRING_INDEX(s.email, '@', -1)) as domain, COUNT(*) as count 
                    FROM sent_emails se
                    JOIN subscribers s ON se.subscriber_id = s.id
                    WHERE DATE_ADD(se.sent_at, INTERVAL 1 HOUR) > NOW()
                    GROUP BY domain";
            $result = $this->conn->query($sql);
            if ($result === false) {
                writeLog('Error in getAllProviderCounts: ' . $this->conn->error, 'ERROR');
                return $counts;
            }
            while ($row = $result->fetch_assoc()) {
                $counts[$row['domain']] = (int)$row['count'];
            }
        } catch (Exception $e) {
            writeLog('Exception in getAllProviderCounts: ' . $e->getMessage(), 'ERROR');
        }
        return $counts;
    }

    /**
     * Gets the next batch of subscribers using rotation with rate limiting
     *
     * This method implements the core rotation logic:
     * 1. Fetches unsent subscribers from database (optimized with LEFT JOIN)
     * 2. Groups them by normalized email domain
     * 3. Checks hourly limits for each provider (precomputed in one query)
     * 4. Skips providers that hit the 100/hour limit
     * 5. Uses round-robin to select from remaining providers
     * 6. Returns the specified batch size
     * 7. Marks selected subscribers as sent
     *
     * @param int $batchSize Number of subscribers to return (default: 5)
     * @return array Array containing:
     *         - 'batch': subscriber records with 'id' and 'email' keys
     *         - 'capped_providers': providers that hit hourly limit
     *         - 'available_providers': providers still under limit
     */
    public function getNextBatch($batchSize = 5) {
        try {
            // Precompute provider counts in one query for efficiency
            $providerCounts = $this->getAllProviderCounts();

            // Query for subscribers who haven't been sent emails yet (optimized with LEFT JOIN)
            $sql = "SELECT s.id, s.email FROM subscribers s 
                    LEFT JOIN sent_emails se ON s.id = se.subscriber_id 
                    WHERE se.subscriber_id IS NULL 
                    LIMIT 10000"; // Limit to prevent excessive memory usage
            $result = $this->conn->query($sql);
            if ($result === false) {
                writeLog('Error in getNextBatch query: ' . $this->conn->error, 'ERROR');
                return [
                    'batch' => [],
                    'capped_providers' => [],
                    'available_providers' => [],
                    'provider_counts' => $providerCounts
                ];
            }

            // Group subscribers by normalized domain
            $subscribers = [];
            while ($row = $result->fetch_assoc()) {
                $domain = normalizeDomain(getDomain($row['email']));
                if (!isset($subscribers[$domain])) {
                    $subscribers[$domain] = [];
                }
                $subscribers[$domain][] = $row;
            }

            // Check hourly limits for each provider using precomputed counts
            $cappedProviders = [];
            $availableProviders = [];

            foreach (array_keys($subscribers) as $domain) {
                $count = $providerCounts[$domain] ?? 0;
                if ($count >= $this->hourlyLimit) {
                    $cappedProviders[] = [
                        'domain' => $domain,
                        'count' => $count,
                        'limit' => $this->hourlyLimit
                    ];
                } else {
                    $availableProviders[] = $domain;
                }
            }

            // round-robin interleaving of available providers ---
            // This ensures fair distribution regardless of subscriber count per provider.
            // We cycle through each available provider repeatedly, taking one subscriber
            // per rotation, rather than assuming equal distribution.
            $batch = [];
            $domainQueues = [];
            $domains = $availableProviders;
            shuffle($domains); // Randomize to avoid bias against alphabetically-last providers

            // Create a queue of unsent subscribers for each available domain
            foreach ($domains as $domain) {
                $domainQueues[$domain] = array_values($subscribers[$domain]);
            }

            // Round-robin through domains, taking one subscriber per rotation
            $currentIndex = 0;
            while (count($batch) < $batchSize) {
                $addedInCycle = false;

                // One full rotation through available domains
                for ($i = 0; $i < count($domains); $i++) {
                    if (count($batch) >= $batchSize) {
                        break;
                    }

                    $domain = $domains[$currentIndex % count($domains)];
                    $currentIndex++;

                    // Skip if domain queue is empty
                    if (empty($domainQueues[$domain])) {
                        continue;
                    }

                    $currentCount = $providerCounts[$domain] ?? 0;

                    // Skip if domain hit hourly limit
                    if ($currentCount >= $this->hourlyLimit) {
                        continue;
                    }

                    // Add subscriber from this domain
                    $sub = array_shift($domainQueues[$domain]);
                    $batch[] = $sub;
                    $providerCounts[$domain] = $currentCount + 1;
                    $addedInCycle = true;
                }

                // If nothing was added in a full cycle, we're done
                if (!$addedInCycle) {
                    break;
                }
            }

            // --- TRICKY LOGIC: Marking as sent ---
            // We immediately mark each selected subscriber as sent to prevent re-sending
            // in future batches, ensuring idempotency and correct rate limiting.
            foreach ($batch as $sub) {
                try {
                    $stmt = $this->conn->prepare("INSERT INTO sent_emails (subscriber_id, sent_at) VALUES (?, NOW())");
                    if ($stmt === false) {
                        writeLog('Prepare failed for sent_emails insert: ' . $this->conn->error, 'ERROR');
                        continue;
                    }
                    $stmt->bind_param("i", $sub['id']);
                    if (!$stmt->execute()) {
                        writeLog('Execute failed for sent_emails insert: ' . $stmt->error, 'ERROR');
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    writeLog('Exception inserting sent_emails: ' . $e->getMessage(), 'ERROR');
                }
            }

            writeLog('Batch processed: ' . count($batch) . ' emails sent.');

            // Return batch with metadata about rate limiting
            return [
                'batch' => $batch,
                'capped_providers' => $cappedProviders,
                'available_providers' => $availableProviders,
                'provider_counts' => $providerCounts
            ];
        } catch (Exception $e) {
            writeLog('Exception in getNextBatch: ' . $e->getMessage(), 'ERROR');
            return [
                'batch' => [],
                'capped_providers' => [],
                'available_providers' => [],
                'provider_counts' => []
            ];
        }
    }

    /**
     * Resets the sent emails tracking for testing purposes
     *
     * This method clears the sent_emails table, allowing the same subscribers
     * to be processed again. Used only for testing/demonstration.
     */
    public function resetSent() {
        try {
            $this->conn->query("DELETE FROM sent_emails");
            writeLog('Sent emails table reset.');
        } catch (Exception $e) {
            writeLog('Error resetting sent_emails: ' . $e->getMessage(), 'ERROR');
        }
    }
}
?>