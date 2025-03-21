# Secure Chat System

A Laravel-based secure chat application implementing end-to-end encryption for private communications.

## Encryption System Overview

This chat application implements true end-to-end encryption (E2E) where only the communicating parties can read the messages. The server never has access to unencrypted private keys or message content.

### Key Encryption Technologies

- RSA-2048 for asymmetric encryption and key exchange
- AES-256-CBC for symmetric encryption via Defuse/Crypto
- Password-based encryption for private key protection
- Hybrid encryption approach for handling large payloads

### How the Encryption Works

#### 1. User Registration & Key Generation

When a user registers:
- A 2048-bit RSA key pair is generated
- The private key is encrypted with the user's password using Defuse/Crypto
- The public key and encrypted private key are stored in the database
- The server never has access to the unencrypted private key

#### 2. Chat Creation & Key Exchange

When two users start a chat:
- A random symmetric key is generated for the chat
- This chat key is encrypted twice - once with each user's public RSA key
- Each encrypted version of the chat key is stored in the database
- Only the respective user can decrypt their version using their password-protected private key

#### 3. Message Encryption

When sending messages:
- The chat's symmetric key is used to encrypt the message content
- The message is stored encrypted in the database
- Neither the server nor unauthorized users can decrypt the message

#### 4. Message Decryption

When reading messages:
- The user provides their password to decrypt their private key (if not cached in session)
- The private key decrypts the chat key
- The chat key decrypts the messages
- For user convenience, the chat key is temporarily cached in the session

### Security Features

- **True E2E Encryption**: The server never has access to unencrypted private keys or messages
- **Password Protection**: Private keys are encrypted with the user's password
- **Hybrid Encryption**: RSA+AES is used for handling data of any size
- **Key Derivation**: Cryptographic salts are used for secure key derivation
- **Session Caching**: Temporary session storage of decrypted chat keys improves usability

## Technical Implementation

The encryption system is primarily implemented in:
- `CryptoService.php`: Core cryptographic operations
- `ChatService.php`: Chat management with encryption
- `UserKeyPair.php`: Model for managing user key pairs
- `Chat.php`: Model for encrypted chat sessions

## Real-time Communication

The chat system uses WebSockets for real-time message delivery:
- Laravel Reverb broadcaster for WebSocket server
- Laravel Echo for client-side WebSocket connections
- Laravel's event broadcasting system for real-time message updates
- End-to-end encryption maintained through secure WebSocket channels

## Security Considerations

- The security of the system relies on users choosing strong passwords
- Session caching improves usability but introduces a security tradeoff
- The system does not currently implement key rotation or forward secrecy

## Requirements

- PHP 8.1+
- Laravel 10.x+
- OpenSSL PHP extension
- Defuse/Crypto library

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## How to run the project

1. Clone the repository
2. Run `cp .env.example .env` to copy env variables
3. Run `composer install` to install backend dependencies
4. Run `npm install && npm run build`to install dependencies and build
5. Generate a new application key with `php artisan key:generate`
6. Run `php artisan migrate` to create the SQLite database
7. To start the application with WebSockets support, run:

   ```
   ./server.sh
   ```
   This script starts both the Laravel web server, the Reverb WebSocket server, and the queue worker to process pending jobs.

Alternatively, you can start the servers manually:
- Web server: `php artisan serve`
- WebSocket server: `php artisan reverb:start`
- Queue worker: `php artisan queue:work`

## Screenshots
Screenshots can be found in the screenshots/ folder.
