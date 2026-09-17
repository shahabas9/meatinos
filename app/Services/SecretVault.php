<?php

declare(strict_types=1);

namespace MeatinOS\Services;

final class SecretVault
{
    private string $key;

    public function __construct(?string $applicationKey = null)
    {
        $source = trim($applicationKey ?? (string) config('app.key', ''));
        if ($source === '' || $source === 'installer-generates-a-random-encryption-key') {
            throw new \RuntimeException('APP_KEY is not configured. Run the production installer or set a unique application encryption key.');
        }
        $this->key = hash('sha256', $source, true);
    }

    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') throw new \InvalidArgumentException('A secret value is required.');
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag, 'MeatinOS-AI-v1', 16);
        if ($ciphertext === false) throw new \RuntimeException('Unable to encrypt the secret.');
        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, 'v1:')) throw new \RuntimeException('Unsupported encrypted secret format.');
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 29) throw new \RuntimeException('The encrypted secret is invalid.');
        $plaintext = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'MeatinOS-AI-v1');
        if ($plaintext === false) throw new \RuntimeException('Unable to decrypt the secret. Check APP_KEY.');
        return $plaintext;
    }
}
