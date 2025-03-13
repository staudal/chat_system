<?php

namespace App\Services;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Defuse\Crypto\KeyProtectedByPassword;
use Exception;
use Illuminate\Support\Facades\Log;

class CryptoService
{
    /**
     * Generate a new RSA key pair based on the user's password.
     *
     * @param string $password
     * @return array
     */
    public function generateKeyPair(string $password): array
    {
        try {
            // Generate RSA key pair
            $config = [
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ];
            
            $res = openssl_pkey_new($config);
            if (!$res) {
                throw new Exception("Failed to generate RSA key pair: " . openssl_error_string());
            }
            
            // Extract private key and convert to PEM
            if (!openssl_pkey_export($res, $privateKey)) {
                throw new Exception("Failed to export private key: " . openssl_error_string());
            }
            
            // Extract public key
            $publicKeyData = openssl_pkey_get_details($res);
            if (!$publicKeyData) {
                throw new Exception("Failed to get public key details: " . openssl_error_string());
            }
            $publicKey = $publicKeyData["key"];
            
            // Create a password-protected key container
            $protectedKey = KeyProtectedByPassword::createRandomPasswordProtectedKey($password);
            $protectedKeyString = $protectedKey->saveToAsciiSafeString();
            
            // Encrypt the private key with the password
            $encryptedPrivateKey = Crypto::encryptWithPassword($privateKey, $password);
            
            // Extract the salt for reference
            $parts = explode('|', $protectedKeyString);
            $salt = $parts[1] ?? '';
            
            return [
                'public_key' => $publicKey,
                'encrypted_private_key' => $encryptedPrivateKey,
                'key_pair_salt' => $salt,
            ];
        } catch (Exception $e) {
            Log::error('Key pair generation failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Encrypt data with a public key.
     *
     * @param string $data
     * @param string $publicKey
     * @return string|null
     */
    public function encryptWithPublicKey(string $data, string $publicKey): ?string
    {
        try {
            $encrypted = null;
            
            // The chat key is likely too long for RSA encryption in one go
            // We'll use a chunk size that's safe for the RSA key size
            $maxLength = 245; // For 2048-bit RSA key, safe length is ~245 bytes
            
            // If data is short enough, encrypt it directly
            if (strlen($data) <= $maxLength) {
                if (!openssl_public_encrypt($data, $encrypted, $publicKey)) {
                    throw new Exception("Public key encryption failed: " . openssl_error_string());
                }
                return base64_encode($encrypted);
            }
            
            // For longer data, we'll use hybrid encryption:
            // 1. Generate a random symmetric key
            $symmetricKey = random_bytes(32); // 256-bit key
            
            // 2. Encrypt the data with the symmetric key using AES
            $ivLength = openssl_cipher_iv_length('aes-256-cbc');
            $iv = openssl_random_pseudo_bytes($ivLength);
            $encryptedData = openssl_encrypt($data, 'aes-256-cbc', $symmetricKey, OPENSSL_RAW_DATA, $iv);
            
            // 3. Encrypt the symmetric key with the public key
            if (!openssl_public_encrypt($symmetricKey, $encryptedKey, $publicKey)) {
                throw new Exception("Public key encryption failed for symmetric key: " . openssl_error_string());
            }
            
            // 4. Combine everything into a single string
            $result = base64_encode($encryptedKey) . '.' . base64_encode($iv) . '.' . base64_encode($encryptedData);
            return $result;
        } catch (Exception $e) {
            Log::error('Public key encryption failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Decrypt data with a private key.
     *
     * @param string $encryptedData
     * @param string $privateKey
     * @return string|null
     */
    public function decryptWithPrivateKey(string $encryptedData, string $privateKey): ?string
    {
        try {
            // Check if this is a hybrid encryption (contains dots)
            if (strpos($encryptedData, '.') !== false) {
                $parts = explode('.', $encryptedData);
                if (count($parts) !== 3) {
                    throw new Exception("Invalid encrypted data format");
                }
                
                $encryptedKey = base64_decode($parts[0]);
                $iv = base64_decode($parts[1]);
                $encryptedData = base64_decode($parts[2]);
                
                // Decrypt the symmetric key
                if (!openssl_private_decrypt($encryptedKey, $symmetricKey, $privateKey)) {
                    throw new Exception("Private key decryption failed: " . openssl_error_string());
                }
                
                // Use the symmetric key to decrypt the data
                $decrypted = openssl_decrypt($encryptedData, 'aes-256-cbc', $symmetricKey, OPENSSL_RAW_DATA, $iv);
                if ($decrypted === false) {
                    throw new Exception("Symmetric decryption failed: " . openssl_error_string());
                }
                
                return $decrypted;
            }
            
            // Simple RSA decryption for short data
            $decrypted = null;
            $encryptedBinary = base64_decode($encryptedData);
            
            if (!openssl_private_decrypt($encryptedBinary, $decrypted, $privateKey)) {
                throw new Exception("Private key decryption failed: " . openssl_error_string());
            }
            
            return $decrypted;
        } catch (Exception $e) {
            Log::error('Private key decryption failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get the decrypted private key for a user.
     *
     * @param string $encryptedPrivateKey
     * @param string $password
     * @return string|null
     */
    public function getDecryptedPrivateKey(string $encryptedPrivateKey, string $password): ?string
    {
        try {
            return Crypto::decryptWithPassword($encryptedPrivateKey, $password);
        } catch (Exception $e) {
            Log::error('Failed to decrypt private key: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Encrypt a message using a symmetric key.
     *
     * @param string $message
     * @param string $keyString
     * @return array
     */
    public function encryptMessage(string $message, string $keyString): array
    {
        try {
            $key = Key::loadFromAsciiSafeString($keyString);
            $encrypted = Crypto::encrypt($message, $key);
            
            // Generate a random IV (not actually used for decryption, as Defuse/Crypto handles that internally)
            // We keep this for compatibility with the database schema
            $iv = bin2hex(random_bytes(16));
            
            return [
                'encrypted_content' => $encrypted,
                'iv' => $iv,
            ];
        } catch (Exception $e) {
            Log::error('Message encryption failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Decrypt a message using a symmetric key.
     *
     * @param string $encryptedMessage
     * @param string $keyString
     * @return string
     */
    public function decryptMessage(string $encryptedMessage, string $keyString): string
    {
        try {
            $key = Key::loadFromAsciiSafeString($keyString);
            return Crypto::decrypt($encryptedMessage, $key);
        } catch (Exception $e) {
            Log::error('Message decryption failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate a shared chat key.
     *
     * @return string
     */
    public function generateChatKey(): string
    {
        $key = Key::createNewRandomKey();
        return $key->saveToAsciiSafeString();
    }
}