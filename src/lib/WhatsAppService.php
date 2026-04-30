<?php

class WhatsAppService
{
    private string $token;
    private string $phoneNumberId;
    private string $apiVersion;
    private string $baseUrl;

    public function __construct()
    {
        $config = parse_ini_file(__DIR__ . '/../config/config.ini', true);

        $this->token         = $config['whatsapp']['meta_whatsapp_token'];
        $this->phoneNumberId = $config['whatsapp']['phone_number_id'];
        $this->apiVersion    = $config['whatsapp']['meta_api_version'] ?? 'v21.0';
        $this->baseUrl       = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
    }

    public function sendTextMessage(string $to, string $message): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $message],
        ]);
    }

    public function sendTemplate(string $to, string $templateName, string $fecha, string $hora, string $languageCode = 'es'): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'       => $templateName,
                'language'   => ['code' => $languageCode],
                'components' => [          
                    [                      
                        'type'       => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $fecha],
                            ['type' => 'text', 'text' => $hora],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function post(array $body): array
    {
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->token,
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);

        $response   = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE); // ← añadir esto

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Error en la petición cURL: $error");
        }

        curl_close($ch);

        $decoded = json_decode($response, true) ?? [];

        if ($httpCode >= 400) {  // ← y esto
            throw new RuntimeException("Error {$httpCode}: " . json_encode($decoded));
        }

        return $decoded;
    }
    
}