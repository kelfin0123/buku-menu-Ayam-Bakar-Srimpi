<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FirestoreProductService
{
    private string $projectId;
    private string $collection;

    public function __construct()
    {
        $this->projectId = (string) config('firebase.project_id');
        $this->collection = (string) config('firebase.products_collection', 'products');

        if ($this->projectId === '') {
            throw new RuntimeException('Firebase project ID belum dikonfigurasi.');
        }

        if ($this->collection === '') {
            throw new RuntimeException('Firebase products collection belum dikonfigurasi.');
        }
    }

    public function getProducts(): array
    {
        Log::info('Firestore connection started', [
            'project_id' => $this->projectId,
            'collection' => $this->collection,
        ]);

        try {
            $documents = $this->loadDocuments();
            $products = [];

            foreach ($documents as $document) {
                $documentId = basename((string) ($document['name'] ?? ''));
                $data = $this->decodeFields($document['fields'] ?? []);

                Log::info('Firestore product document received', [
                    'document_id' => $documentId,
                    'fields' => array_keys($data),
                ]);

                $isActive = $this->booleanValue($data['isActive'] ?? $data['is_active'] ?? true);
                if (!$isActive) {
                    continue;
                }

                $price = $data['price'] ?? 0;
                $products[] = [
                    'id' => (string) ($data['id'] ?? $documentId),
                    'name' => (string) ($data['name'] ?? 'Produk tanpa nama'),
                    'description' => (string) ($data['description'] ?? ''),
                    'category' => (string) ($data['category'] ?? 'Lainnya'),
                    'price' => is_numeric($price) ? (float) $price : 0.0,
                    'stock' => (int) ($data['stock'] ?? 0),
                    'minimumStock' => (int) ($data['minimumStock'] ?? $data['minimum_stock'] ?? 0),
                    'barcode' => (string) ($data['barcode'] ?? ''),
                    'imageUrl' => $data['imageUrl'] ?? $data['image_url'] ?? $data['image'] ?? null,
                    'isActive' => true,
                    'createdAt' => $data['createdAt'] ?? null,
                    'updatedAt' => $data['updatedAt'] ?? null,
                ];
            }

            Log::info('Firestore products loaded', ['count' => count($products)]);

            return $products;
        } catch (\Throwable $e) {
            Log::error('Failed loading Firestore products', [
                'project_id' => $this->projectId,
                'collection' => $this->collection,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function loadDocuments(): array
    {
        $url = sprintf(
            'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/%s',
            rawurlencode($this->projectId),
            rawurlencode($this->collection),
        );
        $documents = [];
        $pageToken = null;

        do {
            $query = array_filter(['pageSize' => 300, 'pageToken' => $pageToken]);
            $response = $this->request()->get($url, $query);
            $response->throw();
            $payload = $response->json();
            $documents = array_merge($documents, $payload['documents'] ?? []);
            $pageToken = $payload['nextPageToken'] ?? null;
        } while ($pageToken);

        return $documents;
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()->timeout(20)->retry(2, 250);
        $credentials = $this->credentials();

        if ($credentials === null) {
            Log::warning('Firestore service account is not configured; attempting access with Firestore security rules.');
            return $request;
        }

        $token = (new ServiceAccountCredentials(
            ['https://www.googleapis.com/auth/datastore'],
            $credentials,
        ))->fetchAuthToken();

        if (empty($token['access_token'])) {
            throw new RuntimeException('Gagal memperoleh access token Firebase service account.');
        }

        return $request->withToken($token['access_token']);
    }

    private function credentials(): ?array
    {
        if ($encoded = config('firebase.credentials_base64')) {
            $decoded = base64_decode((string) $encoded, true);
            $credentials = $decoded === false ? null : json_decode($decoded, true);
            if (!is_array($credentials)) {
                throw new RuntimeException('FIREBASE_CREDENTIALS_BASE64 bukan service account JSON Base64 yang valid.');
            }
            return $credentials;
        }

        if ($path = config('firebase.credentials')) {
            if (!is_file($path) || !is_readable($path)) {
                throw new RuntimeException("Firebase credential file tidak dapat dibaca: {$path}");
            }
            $credentials = json_decode((string) file_get_contents($path), true);
            if (!is_array($credentials)) {
                throw new RuntimeException('Firebase credential file bukan JSON yang valid.');
            }
            return $credentials;
        }

        $email = (string) config('firebase.client_email', '');
        $privateKey = (string) config('firebase.private_key', '');
        if ($email === '' && $privateKey === '') {
            return null;
        }
        if ($email === '' || $privateKey === '') {
            throw new RuntimeException('FIREBASE_CLIENT_EMAIL dan FIREBASE_PRIVATE_KEY harus dikonfigurasi bersama.');
        }

        return array_filter([
            'type' => 'service_account',
            'project_id' => $this->projectId,
            'private_key_id' => config('firebase.private_key_id'),
            'private_key' => $privateKey,
            'client_email' => $email,
            'client_id' => config('firebase.client_id'),
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function decodeFields(array $fields): array
    {
        return array_map(fn (array $value) => $this->decodeValue($value), $fields);
    }

    private function decodeValue(array $value): mixed
    {
        if (array_key_exists('nullValue', $value)) return null;
        if (array_key_exists('stringValue', $value)) return $value['stringValue'];
        if (array_key_exists('integerValue', $value)) return (int) $value['integerValue'];
        if (array_key_exists('doubleValue', $value)) return (float) $value['doubleValue'];
        if (array_key_exists('booleanValue', $value)) return (bool) $value['booleanValue'];
        if (array_key_exists('timestampValue', $value)) return $value['timestampValue'];
        if (array_key_exists('mapValue', $value)) return $this->decodeFields($value['mapValue']['fields'] ?? []);
        if (array_key_exists('arrayValue', $value)) return array_map(fn ($item) => $this->decodeValue($item), $value['arrayValue']['values'] ?? []);
        return null;
    }

    private function booleanValue(mixed $value): bool
    {
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }
        return (bool) $value;
    }
}
