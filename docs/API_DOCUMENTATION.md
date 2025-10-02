# Comprehensive Admissions Management System - API Documentation

## Overview

The Admissions Management System provides a comprehensive RESTful API for managing all aspects of the admissions process. The API is designed to be secure, scalable, and easy to integrate with external systems.

## Base URL

```
https://yourdomain.com/api
```

## Authentication

The API uses session-based authentication. All protected endpoints require a valid session.

### Login
```http
POST /api/auth/login
Content-Type: application/json

{
    "username": "admin@example.com",
    "password": "password123"
}
```

### Logout
```http
POST /api/auth/logout
```

### Check Authentication Status
```http
GET /api/auth/check
```

## Response Format

All API responses follow a consistent format:

```json
{
    "status": "success|error",
    "message": "Human readable message",
    "data": "Response data or null",
    "timestamp": "2024-01-01T12:00:00+00:00"
}
```

## Error Handling

The API uses standard HTTP status codes:

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `405` - Method Not Allowed
- `500` - Internal Server Error

## Endpoints

### Applications

#### List Applications
```http
GET /api/applications?page=1&limit=20&status=pending&program_id=1
```

**Query Parameters:**
- `page` (optional) - Page number (default: 1)
- `limit` (optional) - Items per page (default: 20)
- `status` (optional) - Filter by status
- `program_id` (optional) - Filter by program

#### Get Application
```http
GET /api/applications/{id}
```

#### Create Application
```http
POST /api/applications
Content-Type: application/json

{
    "student_id": 1,
    "program_id": 1,
    "status": "submitted",
    "priority": "medium",
    "notes": "Application notes"
}
```

#### Update Application
```http
PUT /api/applications/{id}
Content-Type: application/json

{
    "status": "under_review",
    "notes": "Updated notes"
}
```

#### Delete Application
```http
DELETE /api/applications/{id}
```

### Students

#### List Students
```http
GET /api/students?page=1&limit=20&search=john&nationality=US
```

**Query Parameters:**
- `page` (optional) - Page number
- `limit` (optional) - Items per page
- `search` (optional) - Search term
- `nationality` (optional) - Filter by nationality

#### Get Student
```http
GET /api/students/{id}
```

#### Create Student
```http
POST /api/students
Content-Type: application/json

{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "phone": "+1234567890",
    "date_of_birth": "1995-01-01",
    "nationality": "US",
    "address": "123 Main St"
}
```

#### Update Student
```http
PUT /api/students/{id}
Content-Type: application/json

{
    "first_name": "John",
    "last_name": "Smith",
    "phone": "+1234567890"
}
```

#### Delete Student
```http
DELETE /api/students/{id}
```

### Programs

#### List Programs
```http
GET /api/programs?active_only=true&level=Undergraduate&department=Computer Science
```

**Query Parameters:**
- `active_only` (optional) - Show only active programs (default: true)
- `level` (optional) - Filter by level
- `department` (optional) - Filter by department

#### Get Program
```http
GET /api/programs/{id}
```

#### Create Program (Admin Only)
```http
POST /api/programs
Content-Type: application/json

{
    "program_name": "Computer Science",
    "program_code": "CS101",
    "level_name": "Undergraduate",
    "department": "Computer Science",
    "description": "Computer Science program",
    "requirements": "High school diploma",
    "duration": 48,
    "credits": 120,
    "application_fee": 50.00
}
```

#### Update Program (Admin Only)
```http
PUT /api/programs/{id}
Content-Type: application/json

{
    "program_name": "Computer Science - Updated",
    "description": "Updated description"
}
```

#### Delete Program (Admin Only)
```http
DELETE /api/programs/{id}
```

### Dashboard

#### Get Dashboard Statistics
```http
GET /api/dashboard/stats
```

Returns comprehensive dashboard statistics including:
- Application counts by status
- Recent applications
- Payment statistics
- Popular programs

### Reports

#### Application Statistics
```http
GET /api/reports/applications?date_from=2024-01-01&date_to=2024-12-31&program_id=1
```

#### Program Statistics
```http
GET /api/reports/programs?date_from=2024-01-01&date_to=2024-12-31
```

#### Application Trends
```http
GET /api/reports/trends?days=30
```

### Payments

#### List Payments
```http
GET /api/payments?page=1&limit=20&status=completed&gateway=stripe
```

#### Get Payment
```http
GET /api/payments/{id}
```

#### Create Payment
```http
POST /api/payments
Content-Type: application/json

{
    "application_id": 1,
    "amount": 50.00,
    "gateway": "stripe",
    "currency": "USD"
}
```

#### Update Payment Status
```http
PUT /api/payments/{id}
Content-Type: application/json

{
    "status": "completed",
    "notes": "Payment processed successfully"
}
```

## Rate Limiting

The API implements rate limiting to prevent abuse:

- **Authenticated users**: 1000 requests per hour
- **Unauthenticated users**: 100 requests per hour

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200
```

## Webhooks

The system supports webhooks for real-time notifications:

### Payment Webhook
```http
POST /api/webhooks/payment
Content-Type: application/json

{
    "event": "payment.completed",
    "data": {
        "payment_id": 123,
        "amount": 50.00,
        "status": "completed"
    }
}
```

### Application Webhook
```http
POST /api/webhooks/application
Content-Type: application/json

{
    "event": "application.status_changed",
    "data": {
        "application_id": 456,
        "old_status": "pending",
        "new_status": "approved"
    }
}
```

## SDK Examples

### JavaScript/Node.js
```javascript
const api = {
    baseUrl: 'https://yourdomain.com/api',
    
    async request(endpoint, options = {}) {
        const response = await fetch(`${this.baseUrl}${endpoint}`, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        return response.json();
    },
    
    async getApplications() {
        return this.request('/applications');
    },
    
    async createApplication(data) {
        return this.request('/applications', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
};
```

### PHP
```php
class AdmissionsAPI {
    private $baseUrl;
    private $sessionId;
    
    public function __construct($baseUrl) {
        $this->baseUrl = $baseUrl;
    }
    
    public function login($username, $password) {
        $response = $this->request('/auth/login', 'POST', [
            'username' => $username,
            'password' => $password
        ]);
        
        if ($response['status'] === 'success') {
            $this->sessionId = $response['data']['token'];
        }
        
        return $response;
    }
    
    public function getApplications($filters = []) {
        $query = http_build_query($filters);
        return $this->request("/applications?$query");
    }
    
    private function request($endpoint, $method = 'GET', $data = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
```

## Testing

### Test Environment
```bash
# Run API tests
php tests/TestRunner.php

# Test specific endpoint
curl -X GET https://yourdomain.com/api/applications
```

### Postman Collection
A Postman collection is available for testing all API endpoints. Import the collection from:
```
docs/postman/Admissions_API.postman_collection.json
```

## Support

For API support and questions:
- Email: api-support@yourdomain.com
- Documentation: https://yourdomain.com/docs/api
- Status Page: https://status.yourdomain.com
