<?php
/**
 * Dashboard Step by Step - Add dependencies gradually
 */

// Step 1: Basic PHP
echo "<!-- Step 1: Basic PHP loaded -->\n";

try {
    // Step 2: Config
    echo "<!-- Step 2: Loading config... -->\n";
    require_once '../config/config.php';
    echo "<!-- Step 2: Config loaded successfully -->\n";
} catch (Exception $e) {
    echo "<!-- Step 2 ERROR: " . htmlspecialchars($e->getMessage()) . " -->\n";
    exit;
}

try {
    // Step 3: Database
    echo "<!-- Step 3: Loading database... -->\n";
    require_once '../config/database.php';
    echo "<!-- Step 3: Database loaded successfully -->\n";
} catch (Exception $e) {
    echo "<!-- Step 3 ERROR: " . htmlspecialchars($e->getMessage()) . " -->\n";
    exit;
}

try {
    // Step 4: Security Manager
    echo "<!-- Step 4: Loading SecurityManager... -->\n";
    require_once '../classes/SecurityManager.php';
    echo "<!-- Step 4: SecurityManager loaded successfully -->\n";
} catch (Exception $e) {
    echo "<!-- Step 4 ERROR: " . htmlspecialchars($e->getMessage()) . " -->\n";
    exit;
}

try {
    // Step 5: Create instances
    echo "<!-- Step 5: Creating instances... -->\n";
    $database = new Database();
    $security = new SecurityManager($database);
    echo "<!-- Step 5: Instances created successfully -->\n";
} catch (Exception $e) {
    echo "<!-- Step 5 ERROR: " . htmlspecialchars($e->getMessage()) . " -->\n";
    exit;
}

try {
    // Step 6: Authentication check
    echo "<!-- Step 6: Checking authentication... -->\n";
    $security->requireAdmin();
    $currentUser = $security->getCurrentUser();
    echo "<!-- Step 6: Authentication successful -->\n";
} catch (Exception $e) {
    echo "<!-- Step 6 ERROR: " . htmlspecialchars($e->getMessage()) . " -->\n";
    exit;
}

// Continue with rest of dashboard...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Step by Step</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            z-index: 1000;
            padding: 20px;
        }
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            padding: 20px;
        }
        .nav-link {
            display: block;
            padding: 10px;
            color: white;
            text-decoration: none;
            margin: 5px 0;
            border-radius: 5px;
        }
        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        .nav-link.active {
            background-color: rgba(255,255,255,0.2);
        }
        .panel-content {
            display: none;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin: 10px 0;
        }
        .panel-content.active {
            display: block;
        }
        #console-output {
            background: #f8f8f8;
            padding: 10px;
            margin: 20px 0;
            font-family: monospace;
            height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h4>Dashboard Step by Step</h4>
        <div class="sidebar-nav">
            <a class="nav-link active" data-panel="overview">
                <i class="bi bi-speedometer2"></i> Overview
            </a>
            <a class="nav-link" data-panel="applications">
                <i class="bi bi-file-earmark-text"></i> Applications
            </a>
            <a class="nav-link" data-panel="students">
                <i class="bi bi-people"></i> Students
            </a>
        </div>
    </div>
    
    <div class="main-content">
        <h1>Dashboard Step by Step</h1>
        
        <div class="alert alert-success">
            <strong>Success!</strong> All PHP dependencies loaded successfully.
            <br>User: <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
        </div>
        
        <div class="panel-content active" id="overview-panel">
            <h3>Overview Panel</h3>
            <p>This is the overview panel content.</p>
        </div>
        
        <div class="panel-content" id="applications-panel">
            <h3>Applications Panel</h3>
            <p>This is the applications panel content.</p>
        </div>
        
        <div class="panel-content" id="students-panel">
            <h3>Students Panel</h3>
            <p>This is the students panel content.</p>
        </div>
        
        <div id="console-output">
            Console output will appear here...
        </div>
        
        <div>
            <a href="dashboard.php" class="btn btn-primary">Back to Main Dashboard</a>
        </div>
    </div>

    <script>
        console.log('Dashboard step by step script starting...');
        
        function addToConsole(message) {
            const output = document.getElementById('console-output');
            const div = document.createElement('div');
            div.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            output.appendChild(div);
            output.scrollTop = output.scrollHeight;
        }
        
        // Override console
        const originalLog = console.log;
        console.log = function(...args) {
            originalLog.apply(console, args);
            addToConsole(args.join(' '));
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');
            
            const navLinks = document.querySelectorAll('.nav-link[data-panel]');
            const panelContents = document.querySelectorAll('.panel-content');
            
            console.log('Found nav links:', navLinks.length);
            console.log('Found panel contents:', panelContents.length);
            
            navLinks.forEach((link, index) => {
                console.log(`Nav link ${index}:`, link.getAttribute('data-panel'));
            });
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const panelName = this.getAttribute('data-panel');
                    console.log('Navigation clicked:', panelName);
                    
                    // Remove active from all links
                    navLinks.forEach(nl => nl.classList.remove('active'));
                    
                    // Hide all panels
                    panelContents.forEach(panel => {
                        panel.classList.remove('active');
                    });
                    
                    // Show target panel
                    const targetPanel = document.getElementById(panelName + '-panel');
                    if (targetPanel) {
                        targetPanel.classList.add('active');
                        this.classList.add('active');
                        console.log('Successfully switched to panel:', panelName);
                    } else {
                        console.error('Panel not found:', panelName + '-panel');
                    }
                });
            });
            
            console.log('Navigation setup complete');
        });
        
        console.log('Script loaded');
    </script>
</body>
</html>
