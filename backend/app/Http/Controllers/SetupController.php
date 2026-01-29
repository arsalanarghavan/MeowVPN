<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Exception;

class SetupController extends Controller
{
    private function updateEnvFile($filePath, $data)
    {
        if (!File::exists($filePath)) {
            throw new Exception("Environment file not found: {$filePath}");
        }

        $envContent = File::get($filePath);
        
        foreach ($data as $key => $value) {
            // Escape special characters but handle empty values
            if (!empty($value)) {
                $value = str_contains($value, ' ') ? "\"$value\"" : $value;
            }
            $pattern = "/^{$key}=.*/m";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }
        
        File::put($filePath, $envContent);
    }

    public function status()
    {
        // Check if setup is marked as complete in .env
        $backendEnvPath = base_path('.env');
        $setupComplete = false;
        
        if (File::exists($backendEnvPath)) {
            $envContent = File::get($backendEnvPath);
            $setupComplete = preg_match('/^SETUP_COMPLETE\s*=\s*true/i', $envContent);
        }
        
        // If not marked complete, check individual steps
        if (!$setupComplete) {
            $adminExists = User::where('role', 'admin')->exists();
            $dbConfigured = $this->checkDatabaseConnection();
            $redisConfigured = $this->checkRedisConnection();
            $botConfigured = $this->checkBotConfiguration();
            
            return response()->json([
                'setup_complete' => false,
                'steps' => [
                    'database' => $dbConfigured,
                    'redis' => $redisConfigured,
                    'admin' => $adminExists,
                    'bot' => $botConfigured,
                ]
            ]);
        }
        
        return response()->json([
            'setup_complete' => true,
            'steps' => [
                'database' => true,
                'redis' => true,
                'admin' => true,
                'bot' => true,
            ]
        ]);
    }

    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkRedisConnection(): bool
    {
        try {
            Redis::connection()->ping();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function checkBotConfiguration(): bool
    {
        $backendEnvPath = base_path('.env');
        if (!File::exists($backendEnvPath)) {
            return false;
        }
        
        $envContent = File::get($backendEnvPath);
        return preg_match('/^TELEGRAM_BOT_TOKEN\s*=\s*.+/m', $envContent);
    }

    /**
     * Validate DNS resolution for domains
     */
    private function validateDNS($domains): array
    {
        $errors = [];
        $serverIP = $this->getServerIP();
        
        foreach ($domains as $domain) {
            $resolvedIPs = gethostbyname($domain);
            
            // Check if DNS resolution failed
            if ($resolvedIPs === $domain) {
                $errors[] = "دامنه {$domain} به درستی resolve نمی‌شود. لطفاً DNS را بررسی کنید.";
                continue;
            }
            
            // Check if domain resolves to this server (optional check)
            // Note: This is a soft check as domains might use CDN or load balancer
            if ($serverIP && $resolvedIPs !== $serverIP) {
                // Just a warning, not an error
                \Log::warning("Domain {$domain} resolves to {$resolvedIPs} but server IP is {$serverIP}");
            }
        }
        
        return $errors;
    }

    /**
     * Get server's public IP address
     */
    private function getServerIP(): ?string
    {
        try {
            // Try to get IP from HTTP request
            $ip = request()->server('SERVER_ADDR');
            if ($ip && $ip !== '127.0.0.1' && $ip !== '::1') {
                return $ip;
            }
            
            // Try external service
            $response = Http::timeout(5)->get('https://api.ipify.org?format=json');
            if ($response->successful()) {
                return $response->json('ip');
            }
        } catch (Exception $e) {
            \Log::warning('Could not determine server IP: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Check if ports are available (80 and 443)
     */
    private function checkPorts(): array
    {
        $errors = [];
        $ports = [80, 443];
        
        foreach ($ports as $port) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
            if ($connection) {
                fclose($connection);
                // Port is open, which is good for our use case
                // We just want to make sure the service can bind to it
            }
        }
        
        // Note: We can't easily check if ports are available from PHP
        // This is more of a warning that should be checked manually
        // The actual check happens when nginx/certbot tries to use the ports
        
        return $errors;
    }

    public function testDatabase(Request $request)
    {
        try {
            $config = $request->validate([
                'host' => 'required',
                'port' => 'required|integer',
                'database' => 'required',
                'username' => 'required',
                'password' => 'required',
            ]);

            // Test connection with temporary config
            config([
                'database.connections.pgsql_test' => [
                    'driver' => 'pgsql',
                    'host' => $config['host'],
                    'port' => $config['port'],
                    'database' => $config['database'],
                    'username' => $config['username'],
                    'password' => $config['password'],
                    'charset' => 'utf8',
                    'prefix' => '',
                    'schema' => 'public',
                ]
            ]);

            DB::connection('pgsql_test')->getPdo();

            return response()->json(['success' => true, 'message' => 'اتصال به دیتابیس موفق بود']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => 'خطا در اتصال: ' . $e->getMessage()], 400);
        }
    }

    public function testRedis(Request $request)
    {
        try {
            $config = $request->validate([
                'host' => 'required',
                'port' => 'required|integer',
                'password' => 'nullable',
            ]);

            // Test with direct connection
            $redis = new \Redis();
            $redis->connect($config['host'], $config['port'], 5);
            
            if (!empty($config['password'])) {
                $redis->auth($config['password']);
            }
            
            $redis->ping();
            $redis->close();

            return response()->json(['success' => true, 'message' => 'اتصال به Redis موفق بود']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => 'خطا در اتصال: ' . $e->getMessage()], 400);
        }
    }

    public function saveDatabase(Request $request)
    {
        try {
            $config = $request->validate([
                'host' => 'required',
                'port' => 'required|integer',
                'database' => 'required',
                'username' => 'required',
                'password' => 'required',
            ]);

            // Test connection first
            config([
                'database.connections.pgsql.host' => $config['host'],
                'database.connections.pgsql.port' => $config['port'],
                'database.connections.pgsql.database' => $config['database'],
                'database.connections.pgsql.username' => $config['username'],
                'database.connections.pgsql.password' => $config['password'],
            ]);

            DB::reconnect('pgsql');
            DB::connection('pgsql')->getPdo();

            // Update backend .env
            $backendEnvPath = base_path('.env');
            $this->updateEnvFile($backendEnvPath, [
                'DB_HOST' => $config['host'],
                'DB_PORT' => $config['port'],
                'DB_DATABASE' => $config['database'],
                'DB_USERNAME' => $config['username'],
                'DB_PASSWORD' => $config['password'],
            ]);

            // Update root .env if exists
            $rootEnvPath = base_path('../.env');
            if (File::exists($rootEnvPath)) {
                $this->updateEnvFile($rootEnvPath, [
                    'DB_HOST' => $config['host'],
                    'DB_PORT' => $config['port'],
                    'DB_DATABASE' => $config['database'],
                    'DB_USERNAME' => $config['username'],
                    'DB_PASSWORD' => $config['password'],
                ]);
            }

            // Run migrations
            Artisan::call('migrate', ['--force' => true]);
            $migrationOutput = Artisan::output();

            return response()->json([
                'success' => true, 
                'message' => 'تنظیمات دیتابیس ذخیره و migrations اجرا شد',
                'migration_output' => $migrationOutput
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function saveRedis(Request $request)
    {
        try {
            $config = $request->validate([
                'host' => 'required',
                'port' => 'required|integer',
                'password' => 'nullable',
            ]);

            // Test connection first
            $redis = new \Redis();
            $redis->connect($config['host'], $config['port'], 5);
            if (!empty($config['password'])) {
                $redis->auth($config['password']);
            }
            $redis->ping();
            $redis->close();

            // Update backend .env
            $backendEnvPath = base_path('.env');
            $envData = [
                'REDIS_HOST' => $config['host'],
                'REDIS_PORT' => $config['port'],
            ];
            
            if (!empty($config['password'])) {
                $envData['REDIS_PASSWORD'] = $config['password'];
            }
            
            $this->updateEnvFile($backendEnvPath, $envData);

            // Update root .env if exists
            $rootEnvPath = base_path('../.env');
            if (File::exists($rootEnvPath)) {
                $this->updateEnvFile($rootEnvPath, $envData);
            }

            // Update runtime config
            config([
                'database.redis.default.host' => $config['host'],
                'database.redis.default.port' => $config['port'],
                'database.redis.default.password' => $config['password'] ?? null,
            ]);

            return response()->json(['success' => true, 'message' => 'تنظیمات Redis ذخیره شد']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function saveDomains(Request $request)
    {
        try {
            $domains = $request->validate([
                'api_domain' => 'required|string',
                'panel_domain' => 'required|string',
                'subscription_domain' => 'required|string',
            ]);

            // Validate DNS (warning only, not blocking at this stage)
            $domainArray = array_values($domains);
            $dnsErrors = $this->validateDNS($domainArray);
            if (!empty($dnsErrors)) {
                \Log::warning('DNS validation warnings: ' . implode(', ', $dnsErrors));
                // Don't fail, but log the warnings
            }

            // Update root .env
            $rootEnvPath = base_path('../.env');
            if (File::exists($rootEnvPath)) {
                $this->updateEnvFile($rootEnvPath, [
                    'API_DOMAIN' => $domains['api_domain'],
                    'PANEL_DOMAIN' => $domains['panel_domain'],
                    'SUBSCRIPTION_DOMAIN' => $domains['subscription_domain'],
                ]);
            }

            // Update backend .env
            $backendEnvPath = base_path('.env');
            $this->updateEnvFile($backendEnvPath, [
                'APP_URL' => 'https://' . $domains['api_domain'],
                'FRONTEND_URL' => 'https://' . $domains['panel_domain'],
            ]);

            // Update Nginx config
            $this->updateNginxConfig($domains);

            return response()->json(['success' => true, 'message' => 'تنظیمات دامنه ذخیره شد', 'domains' => $domains]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function updateNginxConfig($domains)
    {
        $nginxConfigPath = base_path('../docker/nginx/conf.d/default.conf');
        
        if (!File::exists($nginxConfigPath)) {
            return;
        }

        $config = <<<NGINX
# API Server
server {
    listen 80;
    server_name {$domains['api_domain']};
    
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }
    
    location / {
        proxy_pass http://laravel:8000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
    }
}

# Panel Server
server {
    listen 80;
    server_name {$domains['panel_domain']};
    
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }
    
    location / {
        proxy_pass http://frontend:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_cache_bypass \$http_upgrade;
    }
}

# Subscription Server
server {
    listen 80;
    server_name {$domains['subscription_domain']};
    
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }
    
    location / {
        proxy_pass http://laravel:8000/api/sub/;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    }
}
NGINX;

        File::put($nginxConfigPath, $config);
    }

    public function installSSL(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'domains' => 'required|array|min:1',
        ]);

        try {
            // Validate DNS before attempting SSL installation
            $dnsErrors = $this->validateDNS($data['domains']);
            if (!empty($dnsErrors)) {
                return response()->json([
                    'success' => false,
                    'error' => 'خطا در DNS: ' . implode(' ', $dnsErrors) . ' لطفاً DNS را بررسی کنید و مطمئن شوید که دامنه‌ها به IP سرور شما اشاره می‌کنند.'
                ], 400);
            }

            // Check ports (warning only, not blocking)
            $portErrors = $this->checkPorts();
            if (!empty($portErrors)) {
                \Log::warning('Port check warnings: ' . implode(', ', $portErrors));
            }

            // Build certbot command for Docker with timeout
            $domainsArgs = implode(' -d ', $data['domains']);
            $email = $data['email'];
            
            // Use Docker Compose to run certbot with timeout (5 minutes)
            $projectDir = base_path('..');
            $timeout = 300; // 5 minutes timeout
            $command = "cd {$projectDir} && timeout {$timeout} docker compose run --rm certbot certonly --webroot --webroot-path=/var/www/certbot --email {$email} --agree-tos --no-eff-email -d {$domainsArgs} 2>&1";
            
            $startTime = time();
            exec($command, $output, $returnCode);
            $duration = time() - $startTime;

            if ($returnCode === 0) {
                // Update Nginx configuration to use SSL
                $this->updateNginxSSL($data['domains']);
                
                // Reload Nginx
                exec("cd {$projectDir} && docker compose exec -T nginx nginx -s reload 2>&1", $nginxOutput, $nginxReturnCode);
                
                if ($nginxReturnCode !== 0) {
                    \Log::warning('Nginx reload failed: ' . implode("\n", $nginxOutput));
                }
                
                return response()->json([
                    'success' => true, 
                    'message' => 'گواهی SSL با موفقیت نصب شد',
                    'output' => implode("\n", $output),
                    'duration' => $duration
                ]);
            }

            // Check if timeout occurred
            if ($duration >= $timeout) {
                return response()->json([
                    'success' => false,
                    'error' => 'نصب SSL به دلیل timeout متوقف شد. لطفاً DNS و پورت‌های 80 و 443 را بررسی کنید و دوباره تلاش کنید.'
                ], 400);
            }

            return response()->json([
                'success' => false, 
                'error' => 'خطا در نصب SSL: ' . implode("\n", array_slice($output, -10)) // Last 10 lines
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'خطا در نصب SSL: ' . $e->getMessage()
            ], 400);
        }
    }

    private function updateNginxSSL($domains)
    {
        $nginxConfigPath = base_path('../docker/nginx/conf.d/default.conf');
        
        if (!File::exists($nginxConfigPath)) {
            return;
        }

        // Get domain configuration from .env
        $apiDomain = env('API_DOMAIN', $domains[0] ?? 'api.localhost');
        $panelDomain = env('PANEL_DOMAIN', $domains[1] ?? 'panel.localhost');
        $subDomain = env('SUBSCRIPTION_DOMAIN', $domains[2] ?? 'sub.localhost');

        $sslConfigs = [];

        foreach ($domains as $domain) {
            // Determine backend based on domain type
            $proxyPass = 'http://laravel:8000';
            $locationBlock = '';
            
            if (str_contains($domain, 'panel') || $domain === $panelDomain) {
                // Panel domain goes to frontend
                $proxyPass = 'http://frontend:3000';
                $locationBlock = <<<LOCATION
    location / {
        proxy_pass {$proxyPass};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
    }
LOCATION;
            } elseif (str_contains($domain, 'sub') || $domain === $subDomain) {
                // Subscription domain proxies to Laravel subscription endpoint
                $locationBlock = <<<LOCATION
    location / {
        proxy_pass http://laravel:8000/api/sub/;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
LOCATION;
            } else {
                // API domain goes to Laravel
                $locationBlock = <<<LOCATION
    location / {
        proxy_pass http://laravel:8000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
    }
LOCATION;
            }

            $sslConfigs[] = <<<NGINX
# {$domain} - SSL
server {
    listen 443 ssl http2;
    server_name {$domain};
    
    ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
{$locationBlock}
}

# {$domain} - HTTP to HTTPS redirect
server {
    listen 80;
    server_name {$domain};
    
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }
    
    location / {
        return 301 https://\$host\$request_uri;
    }
}
NGINX;
        }

        $fullConfig = implode("\n\n", $sslConfigs);
        File::put($nginxConfigPath, $fullConfig);
    }

    public function createAdmin(Request $request)
    {
        try {
            // Check if admin already exists
            if (User::where('role', 'admin')->exists()) {
                return response()->json([
                    'success' => false, 
                    'error' => 'ادمین قبلاً ایجاد شده است'
                ], 400);
            }

            $data = $request->validate([
                'username' => 'required|string|unique:users,username',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
            ]);

            $admin = User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'admin',
            ]);

            // Generate token for immediate login
            $token = $admin->createToken('admin-token')->plainTextToken;

            return response()->json([
                'success' => true, 
                'message' => 'حساب ادمین با موفقیت ایجاد شد',
                'user' => [
                    'id' => $admin->id,
                    'username' => $admin->username,
                    'email' => $admin->email,
                    'role' => $admin->role,
                ],
                'token' => $token
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function saveBotConfig(Request $request)
    {
        try {
            $data = $request->validate([
                'bot_token' => 'required|string',
            ]);

            // Validate bot token with Telegram API
            $response = Http::get("https://api.telegram.org/bot{$data['bot_token']}/getMe");
            
            if (!$response->successful() || !$response->json('ok')) {
                return response()->json([
                    'success' => false, 
                    'error' => 'توکن ربات نامعتبر است'
                ], 400);
            }

            $botInfo = $response->json('result');

            // Update root .env
            $rootEnvPath = base_path('../.env');
            if (File::exists($rootEnvPath)) {
                $this->updateEnvFile($rootEnvPath, [
                    'TELEGRAM_BOT_TOKEN' => $data['bot_token'],
                    'TELEGRAM_BOT_USERNAME' => $botInfo['username'],
                ]);
            }

            // Update backend .env
            $backendEnvPath = base_path('.env');
            $this->updateEnvFile($backendEnvPath, [
                'TELEGRAM_BOT_TOKEN' => $data['bot_token'],
                'TELEGRAM_BOT_USERNAME' => $botInfo['username'],
            ]);

            return response()->json([
                'success' => true, 
                'message' => 'تنظیمات ربات ذخیره شد',
                'bot' => [
                    'username' => $botInfo['username'],
                    'first_name' => $botInfo['first_name'],
                ]
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function savePaymentConfig(Request $request)
    {
        try {
            $data = $request->validate([
                'zibal_merchant_id' => 'required|string',
                'card_number' => 'nullable|string',
                'card_holder' => 'nullable|string',
            ]);

            // Update root .env
            $rootEnvPath = base_path('../.env');
            if (File::exists($rootEnvPath)) {
                $envData = ['ZIBAL_MERCHANT_ID' => $data['zibal_merchant_id']];
                if (!empty($data['card_number'])) {
                    $envData['CARD_NUMBER'] = $data['card_number'];
                    $envData['CARD_HOLDER'] = $data['card_holder'] ?? '';
                }
                $this->updateEnvFile($rootEnvPath, $envData);
            }

            // Update backend .env
            $backendEnvPath = base_path('.env');
            $envData = ['ZIBAL_MERCHANT_ID' => $data['zibal_merchant_id']];
            if (!empty($data['card_number'])) {
                $envData['CARD_NUMBER'] = $data['card_number'];
                $envData['CARD_HOLDER'] = $data['card_holder'] ?? '';
            }
            $this->updateEnvFile($backendEnvPath, $envData);

            return response()->json(['success' => true, 'message' => 'تنظیمات پرداخت ذخیره شد']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function complete(Request $request)
    {
        try {
            // Verify all required steps are done
            if (!User::where('role', 'admin')->exists()) {
                return response()->json(['success' => false, 'error' => 'ابتدا حساب ادمین را ایجاد کنید'], 400);
            }

            // Mark setup as complete in .env files
            $backendEnvPath = base_path('.env');
            if (File::exists($backendEnvPath)) {
                $this->updateEnvFile($backendEnvPath, [
                    'SETUP_COMPLETE' => 'true',
                ]);
            }

            $rootEnvPath = base_path('../.env');
            if (File::exists($rootEnvPath)) {
                $this->updateEnvFile($rootEnvPath, [
                    'SETUP_COMPLETE' => 'true',
                ]);
            }

            // Clear all caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            // Optimize for production
            Artisan::call('config:cache');
            Artisan::call('route:cache');

            // Restart services
            $projectDir = base_path('..');
            exec("cd {$projectDir} && docker compose restart telegram-bot 2>&1", $output);

            return response()->json([
                'success' => true, 
                'message' => 'راه‌اندازی با موفقیت انجام شد. سیستم آماده استفاده است.'
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
