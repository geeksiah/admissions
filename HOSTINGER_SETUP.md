# Hostinger Setup Guide

## 🚀 **Quick Setup for Hostinger Shared Hosting**

### **Step 1: Create Database in Hostinger Control Panel**

1. **Login to Hostinger Control Panel**
   - Go to your Hostinger account
   - Click on "Manage" for your hosting plan

2. **Create MySQL Database**
   - Go to "Databases" → "MySQL Databases"
   - Click "Create New Database"
   - Database name: `u279576488_admissions` (replace with your username prefix)
   - Click "Create"

3. **Create Database User**
   - Go to "MySQL Users" section
   - Username: `u279576488_admin` (replace with your username prefix)
   - Password: Create a strong password
   - Click "Create User"

4. **Assign User to Database**
   - Go to "Add User to Database"
   - Select your database: `u279576488_admissions`
   - Select your user: `u279576488_admin`
   - **IMPORTANT**: Check "ALL PRIVILEGES"
   - Click "Add"

### **Step 2: Upload Files**

1. **Upload via File Manager**
   - Go to "File Manager" in Hostinger control panel
   - Navigate to `public_html` folder
   - Upload all system files

2. **Set Permissions**
   - Right-click on `config` folder → Permissions → 755
   - Right-click on `uploads` folder → Permissions → 755
   - Right-click on `logs` folder → Permissions → 755
   - Right-click on `backups` folder → Permissions → 755

### **Step 3: Run Installation**

1. **Access Installer**
   - Go to `yourdomain.com/install/`
   - Follow the installation wizard

2. **Database Configuration**
   - **Host**: `localhost`
   - **Database Name**: `u279576488_admissions` (your full database name)
   - **Username**: `u279576488_admin` (your full username)
   - **Password**: (the password you created)

### **Step 4: Common Issues & Solutions**

#### **"Access denied for user" Error**
- **Cause**: User doesn't have proper permissions
- **Solution**: 
  1. Go to "MySQL Databases" in cPanel
  2. Find your database user
  3. Click "Manage" next to the user
  4. Ensure "ALL PRIVILEGES" is checked
  5. Save changes

#### **"Database doesn't exist" Error**
- **Cause**: Database name is incorrect
- **Solution**: Use the full database name with username prefix (e.g., `u279576488_admissions`)

#### **"Connection failed" Error**
- **Cause**: Wrong host or credentials
- **Solution**: 
  - Host should be `localhost`
  - Double-check username and password
  - Ensure database user is assigned to the database

### **Step 5: Post-Installation**

1. **Delete Installer**
   - Remove the `install/` folder for security
   - Or rename it to something else

2. **Set Up SSL**
   - Go to "SSL" in Hostinger control panel
   - Enable "Force HTTPS Redirect"

3. **Configure Email**
   - Set up SMTP settings in the admin panel
   - Use Hostinger's email service

### **Database Configuration Example**

```
Host: localhost
Database Name: u279576488_admissions
Username: u279576488_admin
Password: YourSecurePassword123
```

### **File Structure on Hostinger**

```
public_html/
├── index.php
├── .htaccess
├── config/
├── classes/
├── models/
├── admin/
├── student/
├── uploads/
├── logs/
├── backups/
└── install/ (delete after installation)
```

### **Troubleshooting**

If you encounter issues:

1. **Check Error Logs**
   - Go to `logs/install.log` for detailed error information
   - Check Hostinger error logs in cPanel

2. **Verify Database Connection**
   - Test connection in Hostinger's phpMyAdmin
   - Ensure database and user exist

3. **Check File Permissions**
   - Ensure folders are writable (755 permissions)
   - Check that PHP can create files

4. **Contact Support**
   - If issues persist, contact Hostinger support
   - Provide error logs and configuration details

### **Security Recommendations**

1. **Change Default Passwords**
   - Change admin password after installation
   - Use strong database passwords

2. **Enable HTTPS**
   - Force HTTPS redirect in Hostinger control panel
   - Update APP_URL to use https://

3. **Regular Backups**
   - Set up automated backups
   - Download backups regularly

4. **Keep Updated**
   - Monitor for system updates
   - Update PHP version if needed
