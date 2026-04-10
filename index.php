<?php
/**
 * Newsletter Subscribers Rotator - Main Entry Point
 *
 * This script demonstrates the newsletter rotator functionality by processing
 * batches of subscribers with domain-based rotation and hourly rate limiting
 * to prevent email provider blacklisting.
 *
 * Usage:
 * - Visit http://localhost:8000/index.php for normal operation
 * - Visit http://localhost:8000/index.php?reset=1 to reset sent emails for testing
 *
 * @author Newsletter Rotator Team
 * @version 1.0
 */

// Include required files
require_once 'helpers/helpers.php';

require_once 'services/Rotator.php';

$cappedProvidersList = [];
$nextHourWarning = false;

// Initialize the rotator service
$rotator = new Rotator();

// Handle reset functionality for testing
$resetMessage = "";
if (isset($_GET['reset'])) {
    $rotator->resetSent();
    $resetMessage = '<div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Reset Complete!</strong> All sent email tracking has been cleared. You can now re-run the test.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
}

try {
    $hour = 1;
    $totalBatches = 0;
    $maxBatches = 300; // Run up to 300 batches (1500 emails) to show multi-hour simulation
    
    while ($totalBatches < $maxBatches) {
        $result = $rotator->getNextBatch(5);
        $batch = $result['batch'];
        $totalBatches++;
        
        if (empty($batch)) {
            // Batch is empty - all providers are capped OR no more subscribers
            $availableCount = count($result['available_providers']);
            $cappedCount = count($result['capped_providers']);
            
            if ($cappedCount > 0 && $availableCount === 0 && $totalBatches < $maxBatches) {
                // All providers capped - show capped status before reset
                $batches[] = [
                    'data' => [],
                    'providers' => [],
                    'capped' => $result['capped_providers'],
                    'isCappedStatus' => true,
                    'hour' => $hour,
                    'nextHour' => $hour + 1
                ];
                
                // Then show hour separator (reset)
                $rotator->resetSent(); // Reset for next hour simulation
                $hour++;
                $batches[] = [
                    'data' => [],
                    'providers' => [],
                    'capped' => [],
                    'isHourSeparator' => true,
                    'hour' => $hour
                ];
                continue;
            } else {
                // No more subscribers or max batches reached
                break;
            }
        }
        
        $batchData = [];
        $batchProviders = [];
        foreach ($batch as $sub) {
            $domain = normalizeDomain(getDomain($sub['email']));
            $batchData[] = [
                'email' => $sub['email'],
                'domain' => $domain
            ];
            $batchProviders[$domain] = true;
            
            // Track domain distribution
            if (!isset($domainStats[$domain])) {
                $domainStats[$domain] = 0;
            }
            $domainStats[$domain]++;
        }
        $batches[] = [
            'data' => $batchData,
            'providers' => array_keys($batchProviders),
            'capped' => $result['capped_providers'],
            'hour' => $hour
        ];
    }
} catch (Exception $e) {
    writeLog('Fatal error in batch processing: ' . $e->getMessage(), 'ERROR');
    $resetMessage .= '<div class="alert alert-danger">A fatal error occurred. Please check logs/app.log for details.</div>';
}

// Convert to JSON for JavaScript
$batchesJson = json_encode($batches);
$statsJson = json_encode($domainStats);
$cappedJson = json_encode($cappedProvidersList);
$hourlyLimitJs = $rotator->getHourlyLimit();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter Subscribers Rotator</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        .domain-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .batch-card {
            transition: transform 0.2s, opacity 0.3s;
            opacity: 0;
        }
        .batch-card.visible {
            opacity: 1;
        }
        .batch-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
        }
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .border-left-primary {
            border-left: 4px solid #007bff !important;
        }
        .stats-table {
            margin-top: 20px;
        }
        .progress-status {
            font-size: 0.9rem;
            color: #666;
        }
        .capped-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .limit-reached {
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-envelope-paper-fill me-2"></i>
                Newsletter Rotator
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="?reset=1">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Reset Test
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-3">
                <i class="bi bi-shuffle me-3"></i>
                Smart Email Distribution
            </h1>
            <p class="lead mb-4">
                Preventing email blacklisting through intelligent domain-based rotation with hourly rate limiting
            </p>
            <div class="row justify-content-center">
                <div class="col-md-4 mb-3">
                    <div class="card stats-card border-0">
                        <div class="card-body text-center">
                            <i class="bi bi-shield-check display-4 mb-2"></i>
                            <h5 class="card-title">Anti-Blacklist</h5>
                            <p class="card-text">100 emails/hour per provider enforced</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card border-0">
                        <div class="card-body text-center">
                            <i class="bi bi-speedometer2 display-4 mb-2"></i>
                            <h5 class="card-title">Rate Limited</h5>
                            <p class="card-text">Skips capped providers automatically</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card border-0">
                        <div class="card-body text-center">
                            <i class="bi bi-graph-up display-4 mb-2"></i>
                            <h5 class="card-title">Smart Rotation</h5>
                            <p class="card-text">Round-robin across available providers</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <?php echo $resetMessage; ?>

        <?php if ($nextHourWarning): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Hourly Limits Reached!</strong> Some providers hit their 100 emails/hour limit. 
            <span id="capped-list"></span> would need to wait until the next hour.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Rate Limiting Info -->
                <div class="card shadow-sm mb-4 bg-white">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>
                            Hourly Rate Limiting (100/hour per provider)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="rate-limit-content">
                            <p class="text-muted">Loading rate limit information...</p>
                        </div>
                    </div>
                </div>


                <!-- Progress Bar -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2 text-warning"></i>
                            Processing Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-3">
                            <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <div class="progress-status">
                            <span id="batch-count">Batch 0</span> of <span id="total-batches">0</span> | <span id="email-count">0</span> emails sent
                        </div>
                    </div>
                </div>

                <!-- Live Rotation Test -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h2 class="h4 mb-0">
                            <i class="bi bi-play-circle-fill text-success me-2"></i>
                            Live Rotation Test
                        </h2>
                        <small class="text-muted">
                            📧 670 emails | 100/provider/hour | ~2 hours total | One batch every 2 seconds
                        </small>
                    </div>
                    <div class="card-body">
                        <div id="batch-container">
                            <!-- Batches will be inserted here by JavaScript -->
                        </div>

                        <div id="completion-message" class="alert alert-success mt-4" style="display: none;">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>Test Complete!</strong> All batches have been processed successfully.
                        </div>
                    </div>
                </div>

                <!-- Algorithm Explanation -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-lightbulb text-warning me-2"></i>
                            Algorithm: Rate-Limited Rotation
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center mb-3">
                                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="bi bi-diagram-3 fs-4"></i>
                                </div>
                                <h6 class="mt-2">1. Normalize</h6>
                                <p class="small text-muted">Group by provider</p>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="bg-danger text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="bi bi-shield-check fs-4"></i>
                                </div>
                                <h6 class="mt-2">2. Check Limits</h6>
                                <p class="small text-muted">< 100/hour?</p>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="bi bi-arrow-repeat fs-4"></i>
                                </div>
                                <h6 class="mt-2">3. Round-Robin</h6>
                                <p class="small text-muted">Select from available</p>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="bi bi-send-check fs-4"></i>
                                </div>
                                <h6 class="mt-2">4. Send & Track</h6>
                                <p class="small text-muted">Store in DB</p>
                            </div>
                        </div>

                        <hr>

                        <h6 class="mt-3">Key Features:</h6>
                        <ul>
                            <li><strong>Domain Normalization:</strong> hotmail.com, outlook.com, hotmail.co.uk → 'hotmail'</li>
                            <li><strong>Hourly Limits:</strong> Enforces 100 emails/hour per provider</li>
                            <li><strong>Intelligent Skipping:</strong> Bypasses capped providers, sends to others</li>
                            <li><strong>Round-Robin:</strong> Balances among available (under-limit) providers</li>
                            <li><strong>Duplicate Prevention:</strong> sent_emails table prevents re-sending</li>
                            <li><strong>Graceful Handling:</strong> Stops when all remaining are from capped provider</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">
                <i class="bi bi-code-slash me-2"></i>
                Newsletter Rotator v1.0 | Rate-Limited Rotation | Prevents Blacklisting
            </p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Data from PHP
        const batches = <?php echo $batchesJson; ?>;
        const initialDomainStats = <?php echo $statsJson; ?>;
        const cappedProviders = <?php echo $cappedJson; ?>;
        const hourlyLimit = <?php echo $hourlyLimitJs; ?>;
        
        // Create a mutable copy to track live counts as batches are processed
        const currentDomainCounts = {};
        Object.keys(initialDomainStats).forEach(domain => {
            currentDomainCounts[domain] = 0;
        });

        // Render batches with delay
        let currentBatch = 0;
        const displayInterval = 2000; // 2 seconds between batch displays

        function renderBatch(index) {
            if (index >= batches.length) {
                document.getElementById('completion-message').style.display = 'block';
                return;
            }

            const batchInfo = batches[index];
            
            // Handle all providers capped status
            if (batchInfo.isCappedStatus) {
                const cappedList = batchInfo.capped.map(p => `<li class='text-danger'><strong>${p.domain}</strong>: ${p.count}/${p.limit} ❌</li>`).join('');
                
                // Build list of available providers from the rate limit table
                const allDomains = new Set([
                    ...Object.keys(initialDomainStats),
                    ...Object.keys(currentDomainCounts)
                ]);
                const availableDomains = [];
                allDomains.forEach(domain => {
                    const count = currentDomainCounts[domain] || 0;
                    if (count < hourlyLimit) {
                        availableDomains.push(`<li class='text-success'><strong>${domain}</strong>: ${count}/${hourlyLimit} (${hourlyLimit - count} remaining)</li>`);
                    }
                });
                
                const cappedStatusHTML = `
                    <div class='my-4'>
                        <div class='alert alert-warning p-4'>
                            <div class='text-center mb-3'>
                                <i class="bi bi-pause-circle-fill" style="font-size: 2rem;"></i>
                            </div>
                            <h4 class='mb-3 text-center'>Hourly Rate Limits Reached</h4>
                            
                            <div class='row'>
                                <div class='col-md-6'>
                                    <h6 class='text-danger mb-2'>🔴 Capped Providers (${batchInfo.capped.length}):</h6>
                                    <ul class='list-unstyled small'>${cappedList}</ul>
                                </div>
                                <div class='col-md-6'>
                                    <h6 class='text-success mb-2'>🟢 Still Available (${availableDomains.length}):</h6>
                                    <ul class='list-unstyled small'>${availableDomains.length > 0 ? availableDomains.join('') : '<li class="text-muted">None</li>'}</ul>
                                </div>
                            </div>
                            
                            <hr class='my-3'>
                            
                            <div class='text-center'>
                                <p class='mb-2 text-muted'>Waiting for hourly window reset...</p>
                                <div>
                                    <small class='text-danger'><strong id='countdown-timer'>60:00</strong> until next hour</strong></small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                const container = document.getElementById('batch-container');
                const statusElement = document.createElement('div');
                statusElement.innerHTML = cappedStatusHTML;
                container.appendChild(statusElement);
                
                // Start countdown timer
                startCountdownTimer(3600); // 3600 seconds = 1 hour
                return;
            }
            
            // Handle hour separator
            if (batchInfo.isHourSeparator) {
                // Reset hourly counts for new hour
                Object.keys(currentDomainCounts).forEach(domain => {
                    currentDomainCounts[domain] = 0;
                });
                
                const hourSeparatorHTML = `
                    <div class='my-4'>
                        <div class='d-flex align-items-center'>
                            <div class='flex-grow-1'><hr class='my-0'></div>
                            <div class='px-3 text-center'>
                                <h4 class='mb-0'>
                                    <i class="bi bi-clock-fill text-warning me-2"></i>
                                    Hour ${batchInfo.hour}
                                </h4>
                                <small class="text-muted">Hourly limits reset • Resuming sends</small>
                            </div>
                            <div class='flex-grow-1'><hr class='my-0'></div>
                        </div>
                    </div>
                `;
                const container = document.getElementById('batch-container');
                const hourElement = document.createElement('div');
                hourElement.innerHTML = hourSeparatorHTML;
                container.appendChild(hourElement);
                return;
            }
            
            const batch = batchInfo.data;
            const batchNumber = index + 1;
            
            // Update domain counts for this batch
            batch.forEach(sub => {
                const domain = sub.domain;
                if (currentDomainCounts[domain] !== undefined) {
                    currentDomainCounts[domain]++;
                } else {
                    currentDomainCounts[domain] = 1;
                }
            });
            
            // Show warning if providers are capped
            let cappedWarning = '';
            if (batchInfo.capped && batchInfo.capped.length > 0) {
                const cappedList = batchInfo.capped.map(p => `${p.domain} (${p.count}/${p.limit})`).join(', ');
                cappedWarning = `<div class="capped-warning p-2 mb-2 rounded"><small><i class="bi bi-exclamation-triangle me-1"></i>Capped: ${cappedList}</small></div>`;
            }

            let batchHTML = `
                <div class='batch-card card mb-3 border-left-primary'>
                    <div class='card-header bg-light' style="position: relative;">
                        <h5 class='mb-0'>
                            <i class='bi bi-envelope me-2 text-primary'></i>
                            Batch ${batchNumber}
                            <span class='badge bg-primary ms-2'>5 emails</span>
                            <span class='badge bg-secondary ms-1'>Hour ${batchInfo.hour || 1}</span>
                        </h5>
                    </div>
                    ${cappedWarning}
                    <div class='card-body'>
                        <div class='row g-2'>`;

            // Color map for domains
            const colorMap = {
                'gmail': 'success',
                'hotmail': 'info',
                'yahoo': 'warning',
                'protonmail': 'secondary',
                'tuta': 'danger',
                'seznam': 'dark',
                'gmx': 'primary'
            };

            batch.forEach(sub => {
                const color = colorMap[sub.domain] || 'dark';
                batchHTML += `
                    <div class='col-md-6'>
                        <div class='d-flex align-items-center p-2 bg-light rounded'>
                            <i class='bi bi-person-circle me-2 text-muted'></i>
                            <div class='flex-grow-1'>
                                <small class='text-truncate d-block'>${sub.email}</small>
                                <span class='badge bg-${color} domain-badge'>${sub.domain}</span>
                            </div>
                        </div>
                    </div>`;
            });

            batchHTML += `</div></div></div>`;

            const container = document.getElementById('batch-container');
            const batchElement = document.createElement('div');
            batchElement.innerHTML = batchHTML;
            container.appendChild(batchElement);

            // Trigger animation
            setTimeout(() => {
                batchElement.querySelector('.batch-card').classList.add('visible');
            }, 10);

            // Update progress
            let realBatchCount = 0;
            let totalEmails = 0;
            for (let i = 0; i <= index; i++) {
                if (!batches[i].isHourSeparator && !batches[i].isCappedStatus) {
                    realBatchCount++;
                    totalEmails += 5;
                }
            }
            
            let totalRealBatches = 0;
            for (let i = 0; i < batches.length; i++) {
                if (!batches[i].isHourSeparator && !batches[i].isCappedStatus) {
                    totalRealBatches++;
                }
            }
            
            const percentage = Math.round((realBatchCount / totalRealBatches) * 100);
            document.getElementById('progress-bar').style.width = percentage + '%';
            document.getElementById('progress-bar').textContent = percentage + '%';
            document.getElementById('batch-count').textContent = 'Batch ' + realBatchCount;
            document.getElementById('total-batches').textContent = totalRealBatches;
            document.getElementById('email-count').textContent = totalEmails;

            // Update rate limiting info dynamically
            renderRateLimitInfo();

            currentBatch = index + 1;
        }

        // Render all batches with delays (add extra delay for capped status and hour separators)
        let cumulativeDelay = 0;
        batches.forEach((batch, index) => {
            const delay = cumulativeDelay;
            cumulativeDelay += displayInterval;
            
            // Add extra delay for capped status (1 hour = 3600 seconds) and hour separator (2 seconds)
            if (batch.isCappedStatus) {
                cumulativeDelay += 3600000; // Wait full 1 hour for next hour to arrive
            } else if (batch.isHourSeparator) {
                cumulativeDelay += 2000; // Extra 2 seconds to show hour separator
            }
            
            setTimeout(() => {
                renderBatch(index);
            }, delay);
        });

        // Render rate limit info - now uses current counts instead of initial stats
        function renderRateLimitInfo() {
            let html = `
                <div class='table-responsive'>
                    <table class='table table-sm mb-0'>
                        <thead>
                            <tr>
                                <th>Email Provider</th>
                                <th class='text-center'>This Hour</th>
                                <th class='text-center'>Limit</th>
                                <th class='text-center'>Status</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            // Get all unique domains from both initial stats and current counts
            const allDomains = new Set([
                ...Object.keys(initialDomainStats),
                ...Object.keys(currentDomainCounts)
            ]);
            
            const sortedDomains = Array.from(allDomains).sort();
            
            sortedDomains.forEach(domain => {
                const count = currentDomainCounts[domain] || 0;
                const isCapped = count >= hourlyLimit;
                const status = isCapped 
                    ? '<span class="badge bg-danger"><i class="bi bi-exclamation-circle me-1"></i>CAPPED</span>'
                    : `<span class="badge bg-success">Available (${hourlyLimit - count} left)</span>`;
                
                const countClass = isCapped ? 'limit-reached' : '';
                html += `
                    <tr>
                        <td><strong>${domain}</strong></td>
                        <td class='text-center ${countClass}'>${count}</td>
                        <td class='text-center'>${hourlyLimit}</td>
                        <td class='text-center'>${status}</td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;

            document.getElementById('rate-limit-content').innerHTML = html;
        }

        // Countdown timer for hourly limit waiting period
        function startCountdownTimer(seconds) {
            let remaining = seconds;
            const timerElement = document.getElementById('countdown-timer');
            
            const interval = setInterval(() => {
                remaining--;
                const mins = Math.floor(remaining / 60);
                const secs = remaining % 60;
                const timeStr = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                
                if (timerElement) {
                    timerElement.textContent = timeStr;
                }
                
                if (remaining <= 0) {
                    clearInterval(interval);
                }
            }, 1000); // Update every second
        }

        // Render statistics
        function renderStats() {
            const statsHTML = `
                <div class='table-responsive'>
                    <table class='table table-sm mb-0'>
                        <thead>
                            <tr>
                                <th>Email Provider</th>
                                <th class='text-center'>Total Sent</th>
                                <th class='text-center'>Per Batch (avg)</th>
                                <th class='text-center'>% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            let totalEmails = 0;
            for (const [domain, count] of Object.entries(initialDomainStats)) {
                totalEmails += count;
            }

            for (const [domain, count] of Object.entries(initialDomainStats)) {
                const perBatch = (count / batches.length).toFixed(2);
                const percentage = ((count / totalEmails) * 100).toFixed(1);
                statsHTML += `
                    <tr>
                        <td><strong>${domain}</strong></td>
                        <td class='text-center'><span class="badge bg-info">${count}</span></td>
                        <td class='text-center'>${perBatch}</td>
                        <td class='text-center'>${percentage}%</td>
                    </tr>
                `;
            }

            const finalHTML = statsHTML + `
                        </tbody>
                    </table>
                </div>
                <div class='mt-3 p-3 bg-light rounded'>
                    <p class='mb-2'><strong>Total Emails Processed:</strong> ${totalEmails}</p>
                    <p class='mb-2'><strong>Total Batches:</strong> ${batches.length}</p>
                    <p class='mb-0'><strong>Unique Providers:</strong> ${Object.keys(initialDomainStats).length}</p>
                </div>
            `;

            document.getElementById('stats-content').innerHTML = finalHTML;
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            renderRateLimitInfo();
            renderStats();
            document.getElementById('total-batches').textContent = batches.length;
        });
    </script>
</body>
</html>
