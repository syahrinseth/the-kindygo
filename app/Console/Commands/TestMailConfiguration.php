<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class TestMailConfiguration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {--mailer= : The mailer to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email configuration and connectivity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mailer = $this->option('mailer') ?: Config::get('mail.default');
        
        $this->info("Testing mail configuration...");
        $this->info("Current default mailer: " . Config::get('mail.default'));
        $this->info("Testing mailer: {$mailer}");
        
        try {
            // Test if the mailer configuration exists
            $mailerConfig = Config::get("mail.mailers.{$mailer}");
            
            if (!$mailerConfig) {
                $this->error("Mailer '{$mailer}' is not configured in config/mail.php");
                return 1;
            }
            
            $this->info("Mailer configuration found:");
            $this->line("  Transport: " . ($mailerConfig['transport'] ?? 'N/A'));
            
            // Test specific mailer configurations
            if ($mailer === 'mailgun') {
                $this->testMailgunConfiguration();
            } elseif ($mailer === 'smtp') {
                $this->testSmtpConfiguration();
            }
            
            // Try to send a test email
            $this->info("Attempting to send test email...");
            
            Mail::mailer($mailer)->raw('This is a test email from the mail configuration tester.', function ($message) {
                $message->to('test@example.com')
                        ->subject('Mail Configuration Test');
            });
            
            $this->info("✅ Mail configuration test completed successfully!");
            
        } catch (\Exception $e) {
            $this->error("❌ Mail configuration test failed:");
            $this->error("Error: " . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error("Trace: " . $e->getTraceAsString());
            }
            
            return 1;
        }
        
        return 0;
    }
    
    private function testMailgunConfiguration()
    {
        $this->info("Testing Mailgun configuration...");
        
        $domain = Config::get('services.mailgun.domain');
        $secret = Config::get('services.mailgun.secret');
        $endpoint = Config::get('services.mailgun.endpoint');
        
        $this->line("  Domain: " . ($domain ? '✅ Set' : '❌ Not set'));
        $this->line("  Secret: " . ($secret ? '✅ Set' : '❌ Not set'));
        $this->line("  Endpoint: " . ($endpoint ?: 'Using default'));
        
        if (!$domain || !$secret) {
            $this->warn("Mailgun credentials are not properly configured. Please set MAILGUN_DOMAIN and MAILGUN_SECRET in your .env file.");
        }
    }
    
    private function testSmtpConfiguration()
    {
        $this->info("Testing SMTP configuration...");
        
        $host = Config::get('mail.mailers.smtp.host');
        $port = Config::get('mail.mailers.smtp.port');
        $username = Config::get('mail.mailers.smtp.username');
        
        $this->line("  Host: " . ($host ?: 'Not set'));
        $this->line("  Port: " . ($port ?: 'Not set'));
        $this->line("  Username: " . ($username ? '✅ Set' : '❌ Not set'));
    }
}
