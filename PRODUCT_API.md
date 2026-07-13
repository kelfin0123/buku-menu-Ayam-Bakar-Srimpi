# Product Image Upload API

Endpoint: `POST /api/v1/products`

Content-Type: `multipart/form-data`

Fields:
- `name` (string, required)
- `price` (integer, required)
- `category_id` (integer, required)
- `description` (string, optional)
- `image` (file, optional) — types: `jpg,jpeg,png,webp`, max 5 MB

Response (success):

```
{
  "success": true,
  "message": "Produk berhasil disimpan",
  "data": {
    "id": 1,
    "name": "Ayam Bakar",
    "image": "products/uuid.jpg",
    "image_url": "https://domain.com/storage/products/uuid.jpg"
  }
}
```

Notes:
- Images are stored using the `public` disk under `products/` (storage/app/public/products).
- The database stores only the relative path, e.g. `products/uuid.jpg`.
- The API uses `Storage::url(...)` to build full URLs returned in `image_url`.
- The routes are protected by `auth:sanctum` middleware; provide a valid token.

Flutter example using `MultipartRequest`:

```dart
import 'package:http/http.dart' as http;

final uri = Uri.parse('https://domain.com/api/v1/products');
final request = http.MultipartRequest('POST', uri);
request.headers['Authorization'] = 'Bearer YOUR_TOKEN_HERE';
request.fields['name'] = 'Ayam Bakar';
request.fields['price'] = '25000';
request.fields['category_id'] = '1';

final file = await http.MultipartFile.fromPath('image', '/path/to/image.jpg');
request.files.add(file);

final streamedResponse = await request.send();
final response = await http.Response.fromStream(streamedResponse);
print(response.statusCode);
print(response.body);
```
