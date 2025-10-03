<?php
/**
 * Debug Navigation Test Page
 */
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/SecurityManager.php';

$database = new Database();
$security = new SecurityManager($database);

// Require admin authentication
$security->requireAdmin();

// Get current user info
$currentUser = $security->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation Debug Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Navigation Debug Test</h1>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Test Navigation</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" onclick="testNavigation('overview')">Overview</button>
                            <button class="btn btn-primary" onclick="testNavigation('applications')">Applications</button>
                            <button class="btn btn-primary" onclick="testNavigation('students')">Students</button>
                            <button class="btn btn-primary" onclick="testNavigation('programs')">Programs</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Console Output</h5>
                    </div>
                    <div class="card-body">
                        <div id="console-output" style="height: 300px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
                            <div>Console output will appear here...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <div class="card">
                <div class="card-header">
                    <h5>Panel Content Test</h5>
                </div>
                <div class="card-body">
                    <!-- Test Panels -->
                    <div class="panel-content" id="overview-panel" style="display: none;">
                        <h6>Overview Panel</h6>
                        <p>This is the overview panel content.</p>
                    </div>
                    
                    <div class="panel-content" id="applications-panel" style="display: none;">
                        <h6>Applications Panel</h6>
                        <p>This is the applications panel content.</p>
                    </div>
                    
                    <div class="panel-content active" id="students-panel" style="display: block;">
                        <h6>Students Panel</h6>
                        <p>This is the students panel content.</p>
                    </div>
                    
                    <div class="panel-content" id="programs-panel" style="display: none;">
                        <h6>Programs Panel</h6>
                        <p>This is the programs panel content.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <script>
        // Console override to show output on page
        const originalConsole = {
            log: console.log,
            error: console.error,
            warn: console.warn
        };
        
        function addToConsole(message, type = 'log') {
            const output = document.getElementById('console-output');
            const div = document.createElement('div');
            div.style.color = type === 'error' ? 'red' : type === 'warn' ? 'orange' : 'black';
            div.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            output.appendChild(div);
            output.scrollTop = output.scrollHeight;
        }
        
        console.log = function(...args) {
            originalConsole.log.apply(console, args);
            addToConsole(args.join(' '), 'log');
        };
        
        console.error = function(...args) {
            originalConsole.error.apply(console, args);
            addToConsole(args.join(' '), 'error');
        };
        
        console.warn = function(...args) {
            originalConsole.warn.apply(console, args);
            addToConsole(args.join(' '), 'warn');
        };
        
        // Navigation test function
        function testNavigation(panelName) {
            console.log('Testing navigation for:', panelName);
            
            const navLinks = document.querySelectorAll('.nav-link[data-panel]');
            const panelContents = document.querySelectorAll('.panel-content');
            
            console.log('Found nav links:', navLinks.length);
            console.log('Found panel contents:', panelContents.length);
            
            // Hide all panels
            panelContents.forEach(panel => {
                panel.style.display = 'none';
                panel.classList.remove('active');
            });
            
            // Show target panel
            const targetPanel = document.getElementById(panelName + '-panel');
            if (targetPanel) {
                targetPanel.style.display = 'block';
                targetPanel.classList.add('active');
                console.log('Successfully switched to panel:', panelName);
            } else {
                console.error('Panel not found:', panelName + '-panel');
            }
        }
        
        // Test on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Debug page loaded');
            console.log('Testing panel switching...');
        });
    </script>
</body>
</html>
