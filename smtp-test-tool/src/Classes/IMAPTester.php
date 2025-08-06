<?php

namespace EmailTester\Classes;

use EmailTester\Config\Constants;

class IMAPTester
{
    private string $host;
    private int $port;
    private string $security;
    private string $username;
    private string $password;
    private array $lastError = [];
    private int $responseTime = 0;

    public function __construct(string $host, int $port, string $security, string $username = '', string $password = '')
    {
        $this->host = $host;
        $this->port = $port;
        $this->security = $security;
        $this->username = $username;
        $this->password = $password;
    }

    public function testConnection(): array
    {
        $startTime = microtime(true);
        $result = [
            'success' => false,
            'message' => '',
            'details' => [],
            'response_time' => 0
        ];

        if (!extension_loaded('imap')) {
            $result['message'] = 'PHP IMAP extension is not installed';
            return $result;
        }

        try {
            $connectionString = $this->buildConnectionString();
            
            // Suppress warnings for cleaner error handling
            $connection = @\imap_open($connectionString, $this->username, $this->password, 0, 1, [
                'DISABLE_AUTHENTICATOR' => 'GSSAPI'
            ]);

            if ($connection) {
                $result['success'] = true;
                $result['message'] = 'IMAP connection successful';
                
                // Get mailbox info
                $mailboxInfo = @\imap_mailboxmsginfo($connection);
                if ($mailboxInfo) {
                    $result['details'] = [
                        'total_messages' => $mailboxInfo->Nmsgs,
                        'recent_messages' => $mailboxInfo->Recent,
                        'unread_messages' => $mailboxInfo->Unread,
                        'size' => $mailboxInfo->Size
                    ];
                }

                // Get server info
                $serverInfo = [];
                
                // Try to get server capabilities
                $capabilities = @\imap_capability($connection);
                if ($capabilities) {
                    $serverInfo['capabilities'] = explode(' ', $capabilities);
                }
                
                // Try to get quota information
                $quotaRoot = @\imap_get_quotaroot($connection, 'INBOX');
                if ($quotaRoot && is_array($quotaRoot)) {
                    $serverInfo['quota_root'] = $quotaRoot;
                }
                
                // Get mailbox status
                $status = @\imap_status($connection, $connectionString, SA_ALL);
                if ($status) {
                    $serverInfo['mailbox_status'] = [
                        'messages' => $status->messages ?? 0,
                        'recent' => $status->recent ?? 0,
                        'unseen' => $status->unseen ?? 0,
                        'uidnext' => $status->uidnext ?? 0,
                        'uidvalidity' => $status->uidvalidity ?? 0
                    ];
                }
                
                // Format server info as a readable string
                $serverInfoString = "IMAP Server Connected";
                if (!empty($serverInfo['mailbox_status'])) {
                    $status = $serverInfo['mailbox_status'];
                    $serverInfoString .= " | Messages: {$status['messages']} | Recent: {$status['recent']} | Unseen: {$status['unseen']}";
                }
                
                $result['server_info'] = $serverInfoString;
                
                // Also store capabilities separately for display
                if (!empty($serverInfo['capabilities'])) {
                    $result['capabilities'] = array_slice($serverInfo['capabilities'], 0, 10); // Show first 10 capabilities
                }
                
                @\imap_close($connection);
            } else {
                $error = @\imap_last_error();
                $result['message'] = $error ? $error : 'Failed to connect to IMAP server';
                $this->lastError = ['message' => $result['message']];
            }

        } catch (\Exception $e) {
            $result['message'] = 'IMAP Error: ' . $e->getMessage();
            $this->lastError = [
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];
        }

        $this->responseTime = (int)((microtime(true) - $startTime) * 1000);
        $result['response_time'] = $this->responseTime;

        return $result;
    }

    public function listMailboxes(): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'mailboxes' => []
        ];

        if (!extension_loaded('imap')) {
            $result['message'] = 'PHP IMAP extension is not installed';
            return $result;
        }

        try {
            $connectionString = $this->buildConnectionString();
            $connection = @\imap_open($connectionString, $this->username, $this->password);

            if ($connection) {
                $mailboxes = \imap_list($connection, $connectionString, '*');
                
                if ($mailboxes) {
                    foreach ($mailboxes as $mailbox) {
                        $result['mailboxes'][] = [
                            'name' => $mailbox,
                            'display_name' => $this->getMailboxDisplayName($mailbox)
                        ];
                    }
                    $result['success'] = true;
                    $result['message'] = 'Mailboxes retrieved successfully';
                } else {
                    $result['message'] = 'No mailboxes found or access denied';
                }

                \imap_close($connection);
            } else {
                $error = \imap_last_error();
                $result['message'] = $error ? $error : 'Failed to connect to IMAP server';
            }

        } catch (\Exception $e) {
            $result['message'] = 'Error retrieving mailboxes: ' . $e->getMessage();
        }

        return $result;
    }

    public function checkQuota(): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'quota' => []
        ];

        if (!extension_loaded('imap')) {
            $result['message'] = 'PHP IMAP extension is not installed';
            return $result;
        }

        try {
            $connectionString = $this->buildConnectionString();
            $connection = @\imap_open($connectionString, $this->username, $this->password);

            if ($connection) {
                $quota = \imap_get_quota($connection, 'user.' . $this->username);
                
                if ($quota) {
                    $result['success'] = true;
                    $result['message'] = 'Quota information retrieved';
                    $result['quota'] = [
                        'used' => $quota['usage'] ?? 0,
                        'limit' => $quota['limit'] ?? 0,
                        'usage_percent' => $quota['limit'] > 0 ? round(($quota['usage'] / $quota['limit']) * 100, 2) : 0
                    ];
                } else {
                    // Try quotaroot as fallback
                    $quotaRoot = \imap_get_quotaroot($connection, 'INBOX');
                    if ($quotaRoot) {
                        $result['success'] = true;
                        $result['message'] = 'Quota root information retrieved';
                        $result['quota'] = $quotaRoot;
                    } else {
                        $result['message'] = 'Quota information not available';
                    }
                }

                \imap_close($connection);
            } else {
                $error = \imap_last_error();
                $result['message'] = $error ? $error : 'Failed to connect to IMAP server';
            }

        } catch (\Exception $e) {
            $result['message'] = 'Error checking quota: ' . $e->getMessage();
        }

        return $result;
    }

    public function getRecentMessages(int $limit = 5): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'messages' => []
        ];

        if (!extension_loaded('imap')) {
            $result['message'] = 'PHP IMAP extension is not installed';
            return $result;
        }

        try {
            $connectionString = $this->buildConnectionString();
            $connection = @\imap_open($connectionString, $this->username, $this->password);

            if ($connection) {
                $messageCount = \imap_num_msg($connection);
                
                if ($messageCount > 0) {
                    $start = max(1, $messageCount - $limit + 1);
                    
                    for ($i = $messageCount; $i >= $start && count($result['messages']) < $limit; $i--) {
                        $header = \imap_headerinfo($connection, $i);
                        if ($header) {
                            $result['messages'][] = [
                                'subject' => $header->subject ?? 'No Subject',
                                'from' => $header->fromaddress ?? 'Unknown',
                                'date' => $header->date ?? '',
                                'size' => $header->Size ?? 0,
                                'seen' => !$header->Unseen
                            ];
                        }
                    }
                    
                    $result['success'] = true;
                    $result['message'] = 'Recent messages retrieved';
                } else {
                    $result['message'] = 'No messages found in mailbox';
                    $result['success'] = true;
                }

                \imap_close($connection);
            } else {
                $error = \imap_last_error();
                $result['message'] = $error ? $error : 'Failed to connect to IMAP server';
            }

        } catch (\Exception $e) {
            $result['message'] = 'Error retrieving messages: ' . $e->getMessage();
        }

        return $result;
    }

    private function buildConnectionString(): string
    {
        $flags = [];
        
        switch (strtolower($this->security)) {
            case 'ssl':
                $flags[] = 'ssl';
                break;
            case 'tls':
                $flags[] = 'tls';
                break;
            case 'starttls':
                $flags[] = 'starttls';
                break;
            case 'none':
                $flags[] = 'novalidate-cert';
                break;
        }

        // Add other common flags
        $flags[] = 'novalidate-cert'; // For testing purposes
        
        $flagString = !empty($flags) ? '/' . implode('/', $flags) : '';
        
        return "{{$this->host}:{$this->port}/imap{$flagString}}INBOX";
    }

    private function getMailboxDisplayName(string $mailbox): string
    {
        // Extract the mailbox name from the full path
        $parts = explode('}', $mailbox);
        return end($parts);
    }

    public function getLastError(): array
    {
        return $this->lastError;
    }

    public function getResponseTime(): int
    {
        return $this->responseTime;
    }
}
