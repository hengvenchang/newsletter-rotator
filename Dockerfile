# Use PHP CLI with mysqli extension
FROM php:8.2-cli

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Install mysqli extension
RUN docker-php-ext-install mysqli

# Expose port
EXPOSE 8080

# Start PHP built-in server and pass env vars automatically (Railway injects them into container)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]