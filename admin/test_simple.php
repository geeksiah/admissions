<?php
/**
 * Simple Test Page - No Authentication Required
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Test</title>
    <style>
        .sidebar-nav { background: #f0f0f0; padding: 20px; }
        .nav-link { display: block; padding: 10px; text-decoration: none; color: #333; }
        .nav-link:hover { background: #ddd; }
        .panel-content { display: none; padding: 20px; border: 1px solid #ccc; margin: 10px 0; }
        .panel-content.active { display: block; }
    </style>
</head>
<body>
    <h1>Simple Navigation Test</h1>
    
    <div class="sidebar-nav">
        <a class="nav-link" data-panel="test1">Test 1</a>
        <a class="nav-link" data-panel="test2">Test 2</a>
        <a class="nav-link" data-panel="test3">Test 3</a>
    </div>
    
    <div class="panel-content active" id="test1-panel">
        <h3>Test Panel 1</h3>
        <p>This is test panel 1 content.</p>
    </div>
    
    <div class="panel-content" id="test2-panel">
        <h3>Test Panel 2</h3>
        <p>This is test panel 2 content.</p>
    </div>
    
    <div class="panel-content" id="test3-panel">
        <h3>Test Panel 3</h3>
        <p>This is test panel 3 content.</p>
    </div>
    
    <div id="console-output" style="background: #f8f8f8; padding: 10px; margin: 20px 0; font-family: monospace; height: 200px; overflow-y: auto;">
        Console output will appear here...
    </div>
    
    <script>
        console.log('Simple test script starting...');
        
        function addToConsole(message) {
            const output = document.getElementById('console-output');
            const div = document.createElement('div');
            div.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            output.appendChild(div);
            output.scrollTop = output.scrollHeight;
        }
        
        // Override console.log
        const originalLog = console.log;
        console.log = function(...args) {
            originalLog.apply(console, args);
            addToConsole(args.join(' '));
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            
            const navLinks = document.querySelectorAll('.nav-link');
            const panelContents = document.querySelectorAll('.panel-content');
            
            console.log('Found nav links:', navLinks.length);
            console.log('Found panel contents:', panelContents.length);
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const panelName = this.getAttribute('data-panel');
                    console.log('Clicked:', panelName);
                    
                    // Hide all panels
                    panelContents.forEach(panel => {
                        panel.classList.remove('active');
                    });
                    
                    // Show target panel
                    const targetPanel = document.getElementById(panelName + '-panel');
                    if (targetPanel) {
                        targetPanel.classList.add('active');
                        console.log('Switched to:', panelName);
                    }
                });
            });
        });
    </script>
</body>
</html>
