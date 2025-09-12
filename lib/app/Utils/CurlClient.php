<?php
namespace App\Utils;

class CurlClient
{
    private string $baseUrl;

    public function __construct(string $baseUrl = '')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    private function buildUrl(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * Универсальный POST-запрос (JSON или multipart)
     * @param string $path
     * @param array|string $data
     * @param array $headers
     * @param array $options
     * @return array [$body, $httpCode, $err, $respHeaders]
     */
    public function post(string $path, $data = [], array $headers = [], array $options = []): array
    {
        $url = $this->buildUrl($path);
        $ch  = curl_init($url);

        $hasFile = $this->arrayHasCurlFile(is_array($data) ? $data : []);
        $httpHeaders = $headers ?: ['Accept: application/json'];

        if ($hasFile) {
            // multipart
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } elseif (is_array($data)) {
            // JSON
            $body = json_encode($data, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $httpHeaders[] = 'Content-Type: application/json; charset=utf-8';
        } else {
            // raw body (строка)
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $httpHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => $options['timeout'] ?? 180
        ]);

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) return [null, $code ?: 0, $err, []];

        $rawHeaders = substr($resp, 0, $hdrSize);
        $body       = substr($resp, $hdrSize);
        $respHeaders= $this->parseHeaders($rawHeaders);

        return [$body, $code, $err, $respHeaders];
    }

    /**
     * GET-запрос
     * @return array [$body, $httpCode, $err, $respHeaders]
     */
    public function get(string $path, array $headers = [], array $options = []): array
    {
        $url = $this->buildUrl($path);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers ?: ['Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => $options['timeout'] ?? 60,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) return [null, $code ?: 0, $err, []];

        $rawHeaders = substr($resp, 0, $hdrSize);
        $body       = substr($resp, $hdrSize);
        $respHeaders= $this->parseHeaders($rawHeaders);

        return [$body, $code, $err, $respHeaders];
    }

    private function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
            $p = strpos($line, ':');
            if ($p !== false) {
                $k = strtolower(trim(substr($line, 0, $p)));
                $v = trim(substr($line, $p + 1));
                $headers[$k] = $v;
            }
        }
        return $headers;
    }

    private function arrayHasCurlFile(array $data): bool
    {
        foreach ($data as $v) {
            if ($v instanceof \CURLFile) return true;
            if (is_array($v) && $this->arrayHasCurlFile($v)) return true;
        }
        return false;
    }
}