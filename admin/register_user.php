<?php
// admin/register_user.php
session_start();
require_once '../config/db.php';

// Get owners for dropdown
$owners = [];
try {
    $stmt = $pdo->query("SELECT o.id, u.full_name FROM owners o JOIN users u ON o.user_id = u.id WHERE u.is_active = 1 ORDER BY u.full_name");
    $owners = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching owners: " . $e->getMessage());
}

// Get routes for dropdown
$routes = [];
try {
    $stmt = $pdo->query("SELECT id, route_name, fare_amount FROM routes WHERE is_active = 1 ORDER BY route_name");
    $routes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching routes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register User - Smart Taxi Rank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --dark-color: #1e1e2f;
            --light-color: #f8f9fa;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-bottom: none;
            padding: 1.5rem;
        }
        
        .card-header h4 {
            font-weight: 700;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
            transform: translateY(-2px);
        }
        
        .form-control:hover, .form-select:hover {
            border-color: var(--secondary-color);
        }
        
        .btn {
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        
        .btn:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            20% {
                transform: scale(25, 25);
                opacity: 0.3;
            }
            100% {
                opacity: 0;
                transform: scale(40, 40);
            }
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #495057, #6c757d);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(108, 117, 125, 0.3);
        }
        
        .btn-outline-light {
            border: 2px solid white;
            color: white;
        }
        
        .btn-outline-light:hover {
            background: white;
            color: var(--dark-color);
            transform: translateY(-2px);
        }
        
        h5 {
            color: var(--primary-color);
            font-weight: 700;
            position: relative;
            padding-bottom: 0.5rem;
            margin-top: 1.5rem;
        }
        
        h5:first-of-type {
            margin-top: 0;
        }
        
        h5::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 3px;
        }
        
        .text-muted {
            font-size: 0.85rem;
            margin-top: 0.25rem;
            display: block;
        }
        
        .role-section {
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(63, 55, 201, 0.05));
            padding: 1.5rem;
            border-radius: 15px;
            margin: 1.5rem 0;
            border-left: 4px solid var(--primary-color);
        }
        
        .company-key-section {
            background: linear-gradient(135deg, rgba(248, 150, 30, 0.05), rgba(248, 150, 30, 0.1));
            padding: 1.5rem;
            border-radius: 15px;
            margin: 1.5rem 0;
            border-left: 4px solid var(--warning-color);
            border: 2px solid var(--warning-color);
            box-shadow: 0 0 15px rgba(248, 150, 30, 0.2);
        }
        
        .company-key-section h5 {
            color: var(--warning-color);
        }
        
        .company-key-section h5::after {
            background: linear-gradient(135deg, var(--warning-color), #ffb347);
        }
        
        #ownerFields, #driverFields, #marshalFields {
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .bi {
            margin-right: 0.5rem;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .progress {
            height: 5px;
            margin-top: 1rem;
            border-radius: 5px;
        }
        
        .password-strength {
            height: 5px;
            margin-top: 0.5rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }

        /* Floating labels effect */
        .form-floating {
            position: relative;
        }
        
        .form-floating input:focus ~ label,
        .form-floating input:not(:placeholder-shown) ~ label {
            transform: scale(0.85) translateY(-1.5rem) translateX(0.15rem);
            color: var(--primary-color);
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        }

        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Key verification icon */
        .key-verified {
            color: #28a745;
            animation: popIn 0.3s ease-out;
        }
        
        .key-unverified {
            color: #dc3545;
        }
        
        @keyframes popIn {
            0% {
                transform: scale(0);
            }
            80% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
            }
        }

        /* Warning text */
        .auth-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .auth-warning i {
            color: #856404;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-dark navbar-expand-lg">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-taxi-front"></i>
                Smart Taxi Rank
                <small class="fs-6 text-warning ms-2">Registration Portal</small>
            </span>
            <div class="d-flex">
                <a href="../index.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Login
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Progress Indicator -->
                <div class="progress mb-4" style="height: 5px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 25%;" id="formProgress"></div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 text-white">
                                <i class="bi bi-person-plus-fill"></i>
                                Register New User
                            </h4>
                            <span class="badge bg-light text-dark" id="roleBadge">
                                <i class="bi bi-person"></i> Select Role
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Registration Form -->
                        <form action="process/process_user.php" method="POST" id="registrationForm" class="needs-validation" novalidate>
                            
                            <!-- Company Key Section - REQUIRED for all registrations (Single Password) -->
                            <div class="company-key-section">
                                <h5 class="text-warning">
                                    <i class="bi bi-shield-lock-fill"></i>
                                    COMPANY AUTHORIZATION <span class="text-danger">*</span>
                                </h5>
                                <p class="text-muted small mb-3">
                                    <i class="bi bi-info-circle"></i>
                                    Enter the company key password to authorize this registration.
                                </p>
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="company_key" class="form-label fw-bold">
                                            <i class="bi bi-key-fill"></i>
                                            Company Key Password <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0">
                                                <i class="bi bi-shield-lock"></i>
                                            </span>
                                            <input type="password" class="form-control border-start-0 border-end-0" 
                                                   id="company_key" name="company_key" 
                                                   placeholder="Enter company key password" required>
                                            <button class="btn btn-outline-secondary border-start-0" type="button" 
                                                    id="toggleCompanyKey" onclick="toggleCompanyKeyVisibility()">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <div class="auth-warning mt-2">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <strong>Important:</strong> You must enter the correct company key password to register.
                                            Unauthorized attempts will be logged.
                                        </div>
                                        <div class="invalid-feedback">Company key password is required.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Basic Information Section -->
                            <div class="role-section">
                                <h5>
                                    <i class="bi bi-info-circle"></i>
                                    Basic Information
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="full_name" class="form-label">
                                            <i class="bi bi-person"></i>
                                            Full Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               placeholder="Enter full name" required>
                                        <div class="invalid-feedback">Please enter full name.</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="phone_number" class="form-label">
                                            <i class="bi bi-telephone"></i>
                                            Phone Number <span class="text-danger">*</span>
                                        </label>
                                        <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                               placeholder="081 234 5678" required>
                                        <div class="invalid-feedback">Please enter phone number.</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="username" class="form-label">
                                            <i class="bi bi-envelope"></i>
                                            Username (Email) <span class="text-danger">*</span>
                                        </label>
                                        <input type="email" class="form-control" id="username" name="username" 
                                               placeholder="user@example.com" required>
                                        <div class="invalid-feedback">Please enter valid email.</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="password" class="form-label">
                                            <i class="bi bi-key"></i>
                                            Password <span class="text-danger">*</span>
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="••••••••" minlength="6" required>
                                        <div class="password-strength mt-2"></div>
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i>
                                            Minimum 6 characters
                                        </small>
                                        <div class="invalid-feedback">Password must be at least 6 characters.</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="role" class="form-label">
                                            <i class="bi bi-shield"></i>
                                            User Role <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="" selected disabled>-- Select Role --</option>
                                            <option value="admin" class="text-primary">
                                                Admin
                                            </option>
                                            <option value="marshal" class="text-warning">
                                                Marshal
                                            </option>
                                            <option value="owner" class="text-success">
                                                Owner
                                            </option>
                                            <option value="driver" class="text-info">
                                                Driver
                                            </option>
                                        </select>
                                        <div class="invalid-feedback">Please select a role.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Owner Specific Fields -->
                            <div id="ownerFields" style="display: none;">
                                <h5>
                                    <i class="bi bi-briefcase-fill"></i>
                                    Owner Information
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="id_number" class="form-label">
                                            <i class="bi bi-card-text"></i>
                                            ID Number / Passport <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="id_number" name="id_number" 
                                               placeholder="800101 5084 089">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="bank_name" class="form-label">
                                            <i class="bi bi-bank"></i>
                                            Bank Name
                                        </label>
                                        <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                               placeholder="e.g., FNB, Standard Bank">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="account_number" class="form-label">
                                            <i class="bi bi-credit-card"></i>
                                            Account Number
                                        </label>
                                        <input type="text" class="form-control" id="account_number" name="account_number" 
                                               placeholder="123456789">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="branch_code" class="form-label">
                                            <i class="bi bi-upc-scan"></i>
                                            Branch Code
                                        </label>
                                        <input type="text" class="form-control" id="branch_code" name="branch_code" 
                                               placeholder="250655">
                                    </div>
                                </div>
                            </div>

                            <!-- Driver Specific Fields -->
                            <div id="driverFields" style="display: none;">
                                <h5>
                                    <i class="bi bi-person-badge-fill"></i>
                                    Driver Information
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="driver_id_number" class="form-label">
                                            <i class="bi bi-card-text"></i>
                                            ID Number <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="driver_id_number" name="driver_id_number" 
                                               placeholder="800101 5084 089">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="license_expiry" class="form-label">
                                            <i class="bi bi-calendar"></i>
                                            License Expiry <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" class="form-control" id="license_expiry" name="license_expiry">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="owner_id" class="form-label">
                                            <i class="bi bi-person"></i>
                                            Select Owner <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="owner_id" name="owner_id">
                                            <option value="">-- Select Owner --</option>
                                            <?php foreach ($owners as $owner): ?>
                                                <option value="<?= $owner['id'] ?>">
                                                    <?= htmlspecialchars($owner['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="employment_type" class="form-label">
                                            <i class="bi bi-briefcase"></i>
                                            Employment Type <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="employment_type" name="employment_type">
                                            <option value="">-- Select Type --</option>
                                            <option value="commission">Commission (%)</option>
                                            <option value="wage">Daily Wage (R)</option>
                                            <option value="rental">Rental (R/day)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="payment_rate" class="form-label">
                                            <i class="bi bi-cash"></i>
                                            Payment Rate <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" step="0.01" class="form-control" id="payment_rate" 
                                               name="payment_rate" placeholder="e.g., 20% or R200">
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i>
                                            Percentage or amount per day
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Marshal Specific Fields -->
                            <div id="marshalFields" style="display: none;">
                                <h5>
                                    <i class="bi bi-shield-shaded"></i>
                                    Marshal Information
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="station_point" class="form-label">
                                            <i class="bi bi-pin-map"></i>
                                            Station Point
                                        </label>
                                        <input type="text" class="form-control" id="station_point" name="station_point" 
                                               value="Main Rank" placeholder="e.g., Main Rank, Zone A">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="shift" class="form-label">
                                            <i class="bi bi-clock"></i>
                                            Preferred Shift
                                        </label>
                                        <select class="form-select" id="shift" name="shift">
                                            <option value="morning">Morning (06:00 - 14:00)</option>
                                            <option value="afternoon">Afternoon (14:00 - 22:00)</option>
                                            <option value="night">Night (22:00 - 06:00)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Form Actions -->
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                    Clear Form
                                </button>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="bi bi-check-circle"></i>
                                    Register User
                                </button>
                            </div>
                            
                            <div class="mt-3 text-center text-muted small">
                                <i class="bi bi-shield-lock"></i>
                                All registrations are logged and monitored. Unauthorized access attempts will be recorded.
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle company key password visibility
        function toggleCompanyKeyVisibility() {
            const companyKey = document.getElementById('company_key');
            const toggleBtn = document.getElementById('toggleCompanyKey');
            
            if (companyKey.type === 'password') {
                companyKey.type = 'text';
                toggleBtn.innerHTML = '<i class="bi bi-eye-slash"></i>';
            } else {
                companyKey.type = 'password';
                toggleBtn.innerHTML = '<i class="bi bi-eye"></i>';
            }
        }

        // Form validation
        (function() {
            'use strict';
            
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    // Check if company key is entered
                    const companyKey = document.getElementById('company_key').value;
                    
                    if (!companyKey) {
                        event.preventDefault();
                        event.stopPropagation();
                        alert('⚠️ Please enter the company key password');
                        return false;
                    }
                    
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.querySelector('.password-strength');
            let strength = 0;
            
            if (password.length >= 6) strength += 20;
            if (password.match(/[a-z]+/)) strength += 20;
            if (password.match(/[A-Z]+/)) strength += 20;
            if (password.match(/[0-9]+/)) strength += 20;
            if (password.match(/[$@#&!]+/)) strength += 20;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 40) {
                strengthBar.style.background = 'linear-gradient(90deg, #dc3545, #ff6b6b)';
            } else if (strength < 70) {
                strengthBar.style.background = 'linear-gradient(90deg, #ffc107, #ffdb6b)';
            } else {
                strengthBar.style.background = 'linear-gradient(90deg, #28a745, #6fdb6f)';
            }
        });

        // Show/hide fields based on role selection
        document.getElementById('role').addEventListener('change', function() {
            const role = this.value;
            const roleBadge = document.getElementById('roleBadge');
            const progressBar = document.getElementById('formProgress');
            
            // Update role badge
            const roleNames = {
                'admin': 'Admin',
                'marshal': 'Marshal',
                'owner': 'Owner',
                'driver': 'Driver'
            };
            
            if (role) {
                roleBadge.innerHTML = `<i class="bi bi-person"></i> Registering: ${roleNames[role]}`;
                progressBar.style.width = '100%';
            } else {
                roleBadge.innerHTML = '<i class="bi bi-person"></i> Select Role';
                progressBar.style.width = '50%';
            }
            
            // Hide all role-specific fields first
            document.getElementById('ownerFields').style.display = 'none';
            document.getElementById('driverFields').style.display = 'none';
            document.getElementById('marshalFields').style.display = 'none';
            
            // Remove required attributes
            document.querySelectorAll('#ownerFields input, #ownerFields select, #driverFields input, #driverFields select, #marshalFields input, #marshalFields select').forEach(field => {
                field.required = false;
            });
            
            // Show relevant fields and set required
            if (role === 'owner') {
                document.getElementById('ownerFields').style.display = 'block';
                document.querySelectorAll('#ownerFields input').forEach(field => {
                    if (field.id === 'id_number') field.required = true;
                });
                
            } else if (role === 'driver') {
                document.getElementById('driverFields').style.display = 'block';
                document.querySelectorAll('#driverFields input, #driverFields select').forEach(field => {
                    if (field.id !== 'bank_name' && field.id !== 'account_number' && field.id !== 'branch_code') {
                        field.required = true;
                    }
                });
                
            } else if (role === 'marshal') {
                document.getElementById('marshalFields').style.display = 'block';
            }
        });

        // Auto-format phone number
        document.getElementById('phone_number').addEventListener('input', function(e) {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
            e.target.value = !x[2] ? x[1] : x[1] + ' ' + x[2] + (x[3] ? ' ' + x[3] : '');
        });

        // Submit button loading state
        document.getElementById('registrationForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<span class="spinner"></span> Verifying & Registering...';
            btn.disabled = true;
        });

        // Reset form function
        function resetForm() {
            document.getElementById('registrationForm').reset();
            document.getElementById('roleBadge').innerHTML = '<i class="bi bi-person"></i> Select Role';
            document.getElementById('formProgress').style.width = '25%';
            document.getElementById('ownerFields').style.display = 'none';
            document.getElementById('driverFields').style.display = 'none';
            document.getElementById('marshalFields').style.display = 'none';
        }

        // Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html>