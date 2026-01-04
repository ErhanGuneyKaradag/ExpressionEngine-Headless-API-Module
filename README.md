# ExpressionEngine Headless API Module

A powerful RESTful API module for ExpressionEngine that provides token-based authentication and headless CMS capabilities. Access your channel entries, categories, and custom fields through a clean JSON API.

## Features

- 🔐 **Token-based Authentication** - Secure JWT-like token system
- 📝 **Channel Entries API** - Full access to entries with custom fields
- 🏷️ **Categories API** - Retrieve and filter categories
- 🖼️ **File Field Support** - Automatic URL parsing for images and files
- 📊 **Grid Field Support** - Parse complex grid and file grid fields
- 🔍 **Advanced Filtering** - Filter by category, status, entry ID, and more
- 📄 **Pagination** - Built-in pagination support
- 🚀 **RESTful Design** - Clean and intuitive API endpoints

## Installation

### 1. Upload Module Files

Copy the module files to your ExpressionEngine installation:
```
system/user/addons/api_module/
├── addon.setup.php
├── ext.api_module.php
├── mod.api_module.php
└── upd.api_module.php
```

### 2. Configure .htaccess

Add the following rules to your root `.htaccess` file (before ExpressionEngine's default rules):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    # Pass Authorization header (IMPORTANT!)
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # API routing
    RewriteCond %{REQUEST_URI} ^/api/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php/$1 [L,QSA]

    # ExpressionEngine standard routing (keep existing rules)
    RewriteCond %{REQUEST_URI} !^/(system|themes|images)/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond $1 !^(index\.php)
    RewriteRule ^(.*)$ index.php/$1 [L,QSA]
</IfModule>

# CORS settings (optional, for cross-domain requests)
<IfModule mod_headers.c>
    SetEnvIf Request_URI "^/api/" IS_API
    Header set Access-Control-Allow-Origin "*" env=IS_API
    Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" env=IS_API
    Header set Access-Control-Allow-Headers "Content-Type, Authorization" env=IS_API
    Header set Access-Control-Max-Age "3600" env=IS_API
</IfModule>

# Handle OPTIONS requests quickly
<IfModule mod_rewrite.c>
    RewriteCond %{REQUEST_METHOD} OPTIONS
    RewriteRule ^(.*)$ $1 [R=200,L]
</IfModule>
```

**⚠️ Important Notes:**
- The Authorization header pass-through is **required** for token authentication to work
- CORS settings are optional but recommended if you're calling the API from a different domain
- Keep your existing ExpressionEngine .htaccess rules intact

### 3. Configure ExpressionEngine

Add the following to your `system/user/config/config.php` file:

```php
// Disable CSRF protection for API endpoints
$config['disable_csrf_protection'] = 'y';
```

**⚠️ Security Note:**
- This is required for the API to accept POST requests without CSRF tokens
- The API is protected by token-based authentication instead
- Only disable CSRF if you're using the API module
- Alternative: You can modify the extension to check if the request is to `/api/*` and conditionally disable CSRF only for API routes

**Advanced (Optional):** If you want to keep CSRF enabled for the rest of your site, you can add this condition to your `ext.api_module.php`:

```php
public function route_api()
{
    $uri = ee()->uri->uri_string();
    
    // Disable CSRF only for API routes
    if (strpos($uri, 'api/') === 0) {
        ee()->config->set_item('disable_csrf_protection', 'y');
    }
    
    // ... rest of the code
}
```

### 4. Install the Module

1. Go to your EE Control Panel → **Add-Ons**
2. Find **API Module** and click **Install**
3. The module will automatically:
   - Create the `api_tokens` database table
   - Register the extension hooks
   - Set up routing for `/api/*` endpoints

### 4. Test the Installation

Test if the API is working:

```bash
curl -X POST https://yoursite.com/api/token \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"your_password"}'
```

If you see a token in the response, installation is successful! ✅

## API Endpoints

Base URL: `https://yoursite.com/api/`

### Authentication

The API uses your existing ExpressionEngine user credentials for authentication. You can use any valid EE member account (admin or regular members).

#### Generate Token

**Endpoint:** `POST /api/token`

**Description:** Login with your ExpressionEngine username and password to receive an authentication token.

**Request:**
```json
{
  "username": "your_ee_username",
  "password": "your_ee_password"
}
```

**Example:**
```json
{
  "username": "admin",
  "password": "my_secure_password"
}
```

**Response:**
```json
{
  "success": true,
  "token": "a1b2c3d4e5f6...",
  "expires_in": 86400
}
```

**Notes:**
- Use your **ExpressionEngine Control Panel** login credentials
- Token expires in **24 hours** (86400 seconds)
- The same username/password you use to login to `/system/` admin area
- Token is required for all other API endpoints
- Store the token securely in your application

---

### Entries

#### Get Channel Entries

**Endpoint:** `POST /api/entries`

**Headers:**
```
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

**Basic Request:**
```json
{
  "channel": "blog"
}
```

**Advanced Request:**
```json
{
  "channel": "blog",
  "limit": 20,
  "offset": 0,
  "status": "open",
  "order_by": "entry_date",
  "sort": "desc",
  "entry_id": 123,
  "category_id": 5
}
```

**Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `channel` | string | Yes | - | Channel short name |
| `limit` | integer | No | 10 | Number of entries (max 100) |
| `offset` | integer | No | 0 | Pagination offset |
| `status` | string | No | open | Entry status |
| `order_by` | string | No | entry_date | Order field |
| `sort` | string | No | desc | Sort direction (asc/desc) |
| `entry_id` | integer | No | - | Specific entry ID |
| `category_id` | integer | No | - | Filter by category |

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "entry_id": "324",
      "title": "International Film Studios",
      "url_title": "international-film-studios",
      "entry_date": "1712584200",
      "status": "open",
      "author": {
        "id": "1",
        "username": "admin"
      },
      "categories": [
        {
          "cat_id": "5",
          "cat_name": "Film Studios",
          "cat_url_title": "film-studios",
          "cat_description": "Our film studio facilities",
          "parent_id": "0",
          "group_id": "1",
          "group_name": "Services"
        }
      ],
      "fields": {
        "cover_image": {
          "url": "https://example.com/uploads/studio.jpg",
          "path": "/home/user/public_html/uploads/studio.jpg",
          "filename": "studio.jpg",
          "original": "{filedir_5}studio.jpg"
        },
        "content": "<p>Studio description...</p>",
        "gallery": [
          {
            "row_id": "133",
            "row_order": "0",
            "image": {
              "url": "https://example.com/uploads/img1.jpg",
              "filename": "img1.jpg",
              "upload_id": "5"
            }
          }
        ]
      }
    }
  ],
  "pagination": {
    "total": 45,
    "limit": 10,
    "offset": 0,
    "has_more": true
  }
}
```

---

### Categories

#### Get Categories

**Endpoint:** `POST /api/categories`

**Headers:**
```
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

**Request:**
```json
{
  "group_id": 1,
  "parent_id": 0
}
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `group_id` | integer | No | Filter by category group |
| `cat_id` | integer | No | Get specific category |
| `parent_id` | integer | No | Filter by parent (0 = top level) |

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "cat_id": "5",
      "cat_name": "Film Studios",
      "cat_url_title": "film-studios",
      "cat_description": "Our film studio facilities",
      "cat_order": "1",
      "parent_id": "0",
      "group": {
        "group_id": "1",
        "group_name": "Services"
      },
      "cat_image": {
        "url": "https://example.com/uploads/category.jpg",
        "filename": "category.jpg"
      }
    }
  ],
  "total": 8
}
```

---

## Usage Examples

### cURL Examples

**Get Token:**
```bash
curl -X POST https://yoursite.com/api/token \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "your_password"
  }'
```

**Get Entries:**
```bash
curl -X POST https://yoursite.com/api/entries \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "blog",
    "limit": 5,
    "category_id": 3
  }'
```

**Get Categories:**
```bash
curl -X POST https://yoursite.com/api/categories \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "group_id": 1
  }'
```

### JavaScript (Fetch API)

```javascript
// Get token
const loginResponse = await fetch('https://yoursite.com/api/token', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    username: 'admin',
    password: 'your_password'
  })
});
const { token } = await loginResponse.json();

// Get entries
const entriesResponse = await fetch('https://yoursite.com/api/entries', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    channel: 'blog',
    limit: 10
  })
});
const { data: entries } = await entriesResponse.json();
```

### PHP

```php
// Get token
$ch = curl_init('https://yoursite.com/api/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'username' => 'admin',
    'password' => 'your_password'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = json_decode(curl_exec($ch), true);
$token = $response['token'];

// Get entries
$ch = curl_init('https://yoursite.com/api/entries');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'channel' => 'blog',
    'limit' => 10
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$entries = json_decode(curl_exec($ch), true);
```

---

## Field Type Support

The API automatically parses various ExpressionEngine field types:

| Field Type | Support | Output |
|------------|---------|--------|
| Text | ✅ | String |
| Textarea | ✅ | String/HTML |
| File | ✅ | Object with URL, path, filename |
| Grid | ✅ | Array of rows |
| File Grid | ✅ | Array with parsed file URLs |
| Relationships | ⚠️ | Raw IDs (planned enhancement) |
| Date | ✅ | Unix timestamp |

### File Field Output

```json
{
  "file_id": "42",
  "url": "https://example.com/uploads/image.jpg",
  "path": "/home/user/public_html/uploads/image.jpg",
  "filename": "image.jpg",
  "title": "My Image",
  "description": "Image description",
  "mime_type": "image/jpeg",
  "file_size": "245680"
}
```

### Grid Field Output

```json
[
  {
    "row_id": "1",
    "row_order": "0",
    "col_id_10": "Value 1",
    "col_id_11": "Value 2"
  }
]
```

### File Grid Output

```json
[
  {
    "row_id": "1",
    "row_order": "0",
    "image": {
      "url": "https://example.com/uploads/photo.jpg",
      "filename": "photo.jpg"
    },
    "col_2": "Caption text"
  }
]
```

---

## Error Responses

All error responses follow this format:

```json
{
  "error": "Error message description"
}
```

**Common HTTP Status Codes:**

| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad Request - Missing required parameters |
| 401 | Unauthorized - Invalid or expired token |
| 404 | Not Found - Channel or resource not found |
| 405 | Method Not Allowed - Wrong HTTP method |

---

## Security

- Tokens are hashed using SHA-256 before storage
- Passwords are verified using EE's native authentication (supports EE 2.x - 7.x)
- Expired tokens are automatically cleaned up
- Token expiration: 24 hours
- All API requests require valid authentication (except `/api/token`)

---

## Requirements

- ExpressionEngine 3.x, 4.x, 5.x, 6.x, or 7.x
- PHP 7.2 or higher
- cURL extension (for external API calls)

---

## Roadmap

- [ ] Relationship field parsing
- [ ] File upload endpoint
- [ ] Entry creation/update endpoints
- [ ] Custom field type handlers
- [ ] Rate limiting
- [ ] API versioning
- [ ] Webhook support

---

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## License

MIT License - feel free to use in commercial and personal projects.

---

## Support

For issues, questions, or feature requests, please open an issue on GitHub.

---

## Changelog

### Version 1.0.0
- Initial release
- Token-based authentication
- Entries API with pagination
- Categories API
- File and Grid field support
- Multi-version EE compatibility
