<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Required - Admissions Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .error-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .error-icon {
            font-size: 5rem;
            color: #f59e0b;
            margin-bottom: 1rem;
        }
        
        .error-message {
            font-size: 1.5rem;
            color: #6b7280;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="error-container">
                    <i class="bi bi-person-x error-icon"></i>
                    <h2 class="error-message">Authentication Required</h2>
                    <p class="text-muted mb-4">
                        You need to be logged in to access this page. 
                        Please sign in with your credentials.
                    </p>
                    
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="/login-simple" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            Sign In
                        </a>
                        <a href="/" class="btn btn-outline-primary">
                            <i class="bi bi-house me-2"></i>
                            Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
