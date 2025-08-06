<?php

namespace EmailTester\classes;

use EmailTester\config\Constants;

class PortScanner
{
    private string $host;
    private array $ports;
    private int $timeout;
    private array $results = [];

    public function __construct(string $host, array $ports = [], int $timeout = 5)
    {
        $this->host = $host;
        $this->ports = !empty($ports) ? $ports : array_merge(
            Constants::DEFAULT_SMTP_PORTS,
            Constants::DEFAULT_IMAP_PORTS,
            Constants::DEFAULT_POP3_PORTS
        );
        $this->timeout = $timeout;
    }

    public function scanPorts(): array
    {
        $startTime = microtime(true);
        $results = [
            'success' => true,
            'message' => 'Port scan completed',
            'host' => $this->host,
            'total_ports' => count($this->ports),
            'open_ports' => [],
            'closed_ports' => [],
            'scan_time' => 0,
            'details' => []
        ];

        foreach ($this->ports as $port) {
            $portResult = $this->testPort($port);
            $results['details'][$port] = $portResult;
            
            if ($portResult['open']) {
                $results['open_ports'][] = $port;
            } else {
                $results['closed_ports'][] = $port;
            }
        }

        $results['scan_time'] = (int)((microtime(true) - $startTime) * 1000);
        $this->results = $results;

        return $results;
    }

    public function testPort(int $port): array
    {
        $startTime = microtime(true);
        $result = [
            'port' => $port,
            'open' => false,
            'service' => $this->identifyService($port),
            'response_time' => 0,
            'banner' => '',
            'error' => ''
        ];

        try {
            $socket = @fsockopen($this->host, $port, $errno, $errstr, $this->timeout);
            
            if ($socket) {
                $result['open'] = true;
                
                // Try to read banner information
                stream_set_timeout($socket, 2);
                $banner = @fread($socket, 1024);
                if ($banner) {
                    $result['banner'] = trim($banner);
                }
                
                fclose($socket);
            } else {
                $result['error'] = "Connection failed: $errstr ($errno)";
            }
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        $result['response_time'] = (int)((microtime(true) - $startTime) * 1000);
        return $result;
    }

    public function scanCommonEmailPorts(): array
    {
        $emailPorts = [
            25 => 'SMTP',
            465 => 'SMTPS',
            587 => 'SMTP Submission',
            143 => 'IMAP',
            993 => 'IMAPS',
            110 => 'POP3',
            995 => 'POP3S'
        ];

        $results = [
            'success' => true,
            'message' => 'Email port scan completed',
            'host' => $this->host,
            'services' => []
        ];

        foreach ($emailPorts as $port => $service) {
            $portResult = $this->testPort($port);
            $portResult['service_name'] = $service;
            $results['services'][$port] = $portResult;
        }

        return $results;
    }

    public function detectEmailServer(): array
    {
        $detection = [
            'smtp_ports' => [],
            'imap_ports' => [],
            'pop3_ports' => [],
            'security_protocols' => [],
            'server_type' => 'Unknown'
        ];

        $emailPorts = [
            25 => ['type' => 'smtp', 'security' => 'none'],
            465 => ['type' => 'smtp', 'security' => 'ssl'],
            587 => ['type' => 'smtp', 'security' => 'tls'],
            143 => ['type' => 'imap', 'security' => 'none'],
            993 => ['type' => 'imap', 'security' => 'ssl'],
            110 => ['type' => 'pop3', 'security' => 'none'],
            995 => ['type' => 'pop3', 'security' => 'ssl']
        ];

        foreach ($emailPorts as $port => $info) {
            $result = $this->testPort($port);
            if ($result['open']) {
                $detection[$info['type'] . '_ports'][] = [
                    'port' => $port,
                    'security' => $info['security'],
                    'banner' => $result['banner']
                ];

                if (!in_array($info['security'], $detection['security_protocols'])) {
                    $detection['security_protocols'][] = $info['security'];
                }

                // Try to identify server type from banner
                if ($result['banner']) {
                    $serverType = $this->identifyServerFromBanner($result['banner']);
                    if ($serverType !== 'Unknown') {
                        $detection['server_type'] = $serverType;
                    }
                }
            }
        }

        return $detection;
    }

    private function identifyService(int $port): string
    {
        $services = [
            25 => 'SMTP',
            465 => 'SMTPS (SSL)',
            587 => 'SMTP Submission',
            2525 => 'SMTP (Alternative)',
            143 => 'IMAP',
            993 => 'IMAPS (SSL)',
            110 => 'POP3',
            995 => 'POP3S (SSL)',
            80 => 'HTTP',
            443 => 'HTTPS',
            21 => 'FTP',
            22 => 'SSH',
            23 => 'Telnet',
            53 => 'DNS'
        ];

        return $services[$port] ?? 'Unknown';
    }

    private function identifyServerFromBanner(string $banner): string
    {
        $banner = strtolower($banner);
        
        if (strpos($banner, 'microsoft') !== false) {
            return 'Microsoft Exchange';
        } elseif (strpos($banner, 'postfix') !== false) {
            return 'Postfix';
        } elseif (strpos($banner, 'sendmail') !== false) {
            return 'Sendmail';
        } elseif (strpos($banner, 'exim') !== false) {
            return 'Exim';
        } elseif (strpos($banner, 'dovecot') !== false) {
            return 'Dovecot';
        } elseif (strpos($banner, 'courier') !== false) {
            return 'Courier';
        } elseif (strpos($banner, 'gmail') !== false) {
            return 'Gmail';
        } elseif (strpos($banner, 'outlook') !== false || strpos($banner, 'office365') !== false) {
            return 'Outlook/Office365';
        }

        return 'Unknown';
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function generateReport(): array
    {
        if (empty($this->results)) {
            $this->scanPorts();
        }

        $report = [
            'summary' => [
                'host' => $this->host,
                'total_ports_scanned' => count($this->ports),
                'open_ports' => count($this->results['open_ports']),
                'closed_ports' => count($this->results['closed_ports']),
                'scan_duration' => $this->results['scan_time'] . 'ms'
            ],
            'open_services' => [],
            'recommendations' => []
        ];

        foreach ($this->results['open_ports'] as $port) {
            $detail = $this->results['details'][$port];
            $report['open_services'][] = [
                'port' => $port,
                'service' => $detail['service'],
                'response_time' => $detail['response_time'] . 'ms',
                'banner' => $detail['banner'] ?: 'No banner'
            ];
        }

        // Generate recommendations
        $openPorts = $this->results['open_ports'];
        
        if (in_array(25, $openPorts) && !in_array(587, $openPorts)) {
            $report['recommendations'][] = 'Consider using port 587 for SMTP submission instead of port 25';
        }
        
        if (in_array(143, $openPorts) && !in_array(993, $openPorts)) {
            $report['recommendations'][] = 'Consider enabling IMAPS (port 993) for secure IMAP connections';
        }

        if (in_array(110, $openPorts) && !in_array(995, $openPorts)) {
            $report['recommendations'][] = 'Consider enabling POP3S (port 995) for secure POP3 connections';
        }

        return $report;
    }
}
