<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SharedSSE
{
    public function __construct(private ParameterBagInterface $params)
    {
    }

    public function handle(string $key): StreamedResponse
    {
        $base = $this->params->get('ZERO_INTRUSION_DOMAIN');
        $url = "$base/api/credential-hub/domain/read/approval-challange/$key";

        return new StreamedResponse(function () use ($url) {

            session_write_close();

            $client = HttpClient::create();

            $response = $client->request('GET', $url, [
                'buffer' => false,
            ]);

            foreach ($client->stream($response) as $chunk) {

                echo $chunk->getContent();

                @ob_flush();
                flush();
            }

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}