<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Models\UserOtpModel;
use App\Models\UserApiTokenModel;
use App\Libraries\Twilio;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;

class AuthController extends ResourceController
{
    use ResponseTrait;

    protected $userModel;
    protected $userOtpModel;
    protected $userApiTokenModel;
    protected $db;
    protected $twilioService;
    protected $developmentMode = false;
    protected $developmentOtp = '123456';
    protected $developmentPhone = '+51999309748';

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->userOtpModel = new UserOtpModel();
        $this->userApiTokenModel = new UserApiTokenModel();
        $this->db = \Config\Database::connect();
        $this->twilioService = new Twilio();
        
        // Habilitar modo desarrollo si TWILIO_ENABLED está deshabilitado
        $this->developmentMode = getenv('TWILIO_ENABLED') !== 'true';
    }

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        
        // Create API log directory if it doesn't exist
        if (!is_dir(WRITEPATH . 'logs/api')) {
            mkdir(WRITEPATH . 'logs/api', 0755, true);
        }
        
        // Log API request to dedicated file
        $logFile = WRITEPATH . 'logs/api/requests-' . date('Y-m-d') . '.log';
        $logMessage = date('Y-m-d H:i:s') . ' - URI: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown') . 
                     ', Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . 
                     ', User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . 
                     ', Controller: ' . get_class($this) . 
                     ', Action: ' . ($_SERVER['PATH_INFO'] ?? 'unknown') . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // Desactivar session completamente
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        // Log para depuración
        log_message('debug', 'API Request: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown') . ' - Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
        log_message('debug', 'Controller: ' . get_class($this) . ' - Action: ' . ($_SERVER['PATH_INFO'] ?? 'unknown'));
    }

    /**
     * Request OTP for login
     */
    public function requestOtp()
    {
        try {
            // Get request data
            $rawBody = file_get_contents('php://input');
            $jsonData = json_decode($rawBody, true);
            
            $email = $_POST['email'] ?? $jsonData['email'] ?? null;
            $phone = $_POST['phone'] ?? $jsonData['phone'] ?? null;
            $organizationCode = $_POST['organization_code'] ?? $jsonData['organization_code'] ?? null;
            $deviceInfo = $_POST['device_info'] ?? $jsonData['device_info'] ?? 'Unknown Device';
            
            // Validate required fields
            if (empty($phone) && empty($email)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Either phone or email is required'
                ]);
            }

            // If phone is provided, check if user exists and get organizations
            if (!empty($phone)) {
                $users = $this->userModel->getOrganizationsByPhone($phone);
                
                if (empty($users)) {
                    return $this->response->setStatusCode(404)->setJSON([
                        'status' => 'error',
                        'message' => 'No user found with this phone number'
                    ]);
                }
                
                // If user has multiple organizations and no organization_code provided
                if (count($users) > 1 && empty($organizationCode)) {
                    $organizations = array_map(function($user) {
                        return [
                            'code' => $user['org_code'],
                            'name' => $user['org_name']
                        ];
                    }, $users);
                    
                    return $this->response->setJSON([
                        'status' => 'multiple_organizations',
                        'message' => 'Please specify which organization you want to access',
                        'data' => [
                            'organizations' => $organizations
                        ]
                    ]);
                }
                
                // If organization_code is provided, validate it
                if (!empty($organizationCode)) {
                    $validOrg = false;
                    foreach ($users as $user) {
                        if ($user['org_code'] === $organizationCode) {
                            $validOrg = true;
                            break;
                        }
                    }
                    
                    if (!$validOrg) {
                        return $this->response->setStatusCode(400)->setJSON([
                            'status' => 'error',
                            'message' => 'Invalid organization code'
                        ]);
                    }
                }
            }

            // Generate OTP - Use hardcoded OTP in development mode for specific phone
            if ($this->developmentMode && $phone === $this->developmentPhone) {
                $otp = $this->developmentOtp;
                log_message('info', "Development mode: Using hardcoded OTP {$otp} for phone {$phone}");
            } else {
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            }
            
            // Store OTP in database
            $otpData = [
                'phone' => $phone,
                'email' => $email,
                'code' => $otp,
                'organization_code' => $organizationCode,
                'device_info' => $deviceInfo,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->userOtpModel->insert($otpData);
            
            // Send OTP via SMS if phone is provided
            if (!empty($phone)) {
                try {
                    $message = "Your verification code is: {$otp}. Valid for 5 minutes.";
                    $result = $this->twilioService->sendSms($phone, $message);
                    
                    if (!$result['success']) {
                        log_message('error', "Failed to send OTP via SMS: " . ($result['message'] ?? 'Unknown error'));
                        return $this->response->setStatusCode(500)->setJSON([
                            'status' => 'error',
                            'message' => 'Failed to send OTP via SMS',
                            'details' => $result['message'] ?? 'Unknown error'
                        ]);
                    }
                    
                    // Update delivery status
                    $this->userOtpModel->updateDeliveryStatus(
                        $phone,
                        $otp,
                        'sent',
                        'Twilio SID: ' . ($result['sid'] ?? 'unknown')
                    );
                    
                    log_message('info', "OTP sent successfully to {$phone}");
                } catch (\Exception $e) {
                    log_message('error', 'Twilio error: ' . $e->getMessage());
                    return $this->response->setStatusCode(500)->setJSON([
                        'status' => 'error',
                        'message' => 'Failed to send OTP via SMS',
                        'details' => $e->getMessage()
                    ]);
                }
            }
            
            // Send OTP via email if email is provided
            if (!empty($email)) {
                // TODO: Implement email sending
                log_message('info', "Email OTP functionality not implemented yet");
            }
            
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'OTP sent successfully',
                'data' => [
                    'email' => $email,
                    'phone' => $phone,
                    'organization_code' => $organizationCode,
                    'device_info' => $deviceInfo,
                    'expires_in' => '5 minutes'
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Error in requestOtp: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Internal server error',
                'details' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verify OTP and generate JWT token
     */
    public function verifyOtp()
    {
        try {
            // Get request data
            $rawBody = file_get_contents('php://input');
            $jsonData = json_decode($rawBody, true);
            
            $email = $_POST['email'] ?? $jsonData['email'] ?? null;
            $phone = $_POST['phone'] ?? $jsonData['phone'] ?? null;
            $code = $_POST['code'] ?? $jsonData['code'] ?? null;
            $organizationCode = $_POST['organization_code'] ?? $jsonData['organization_code'] ?? null;
            $deviceInfo = $_POST['device_info'] ?? $jsonData['device_info'] ?? 'Unknown Device';
            
            // Validate required fields
            if (empty($code)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'OTP code is required'
                ]);
            }

            if (empty($phone) && empty($email)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Either phone or email is required'
                ]);
            }

            // Get user data
            if (!empty($phone)) {
                $users = $this->userModel->getOrganizationsByPhone($phone);
                
                if (empty($users)) {
                    return $this->response->setStatusCode(404)->setJSON([
                        'status' => 'error',
                        'message' => 'No user found with this phone number'
                    ]);
                }
                
                // If user has multiple organizations, organization_code is required
                if (count($users) > 1 && empty($organizationCode)) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'status' => 'error',
                        'message' => 'Organization code is required for users with multiple organizations'
                    ]);
                }
                
                // Get user data for the specified organization
                $userData = null;
                foreach ($users as $user) {
                    if (empty($organizationCode) || $user['org_code'] === $organizationCode) {
                        $userData = $user;
                        break;
                    }
                }
                
                if (!$userData) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'status' => 'error',
                        'message' => 'Invalid organization code'
                    ]);
                }
            }

            // Verify OTP
            $otpData = $this->userOtpModel->verifyOTP($phone, $email, $code, $organizationCode);
            
            if (!$otpData) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid or expired OTP code'
                ]);
            }
            
            // Generate API token
            $token = bin2hex(random_bytes(32)); // 64 caracteres hexadecimales
            
            // Store token in database using direct query
            $sql = "INSERT INTO user_api_tokens (user_id, token, name, expires_at, created_at) VALUES (?, ?, ?, ?, ?)";
            $this->db->query($sql, [
                $userData['id'],
                $token,
                'OTP Login', // Nombre descriptivo para el token
                date('Y-m-d H:i:s', strtotime('+30 days')),
                date('Y-m-d H:i:s')
            ]);
            
            // Return success response with token and user data
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'OTP verified successfully',
                'data' => [
                    'token' => $token,
                    'expires_in' => '30 days',
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $userData['id'],
                        'name' => $userData['name'],
                        'email' => $userData['email'],
                        'phone' => $userData['phone'],
                        'role' => $userData['role'],
                        'organization' => [
                            'id' => $userData['org_id'],
                            'name' => $userData['org_name'],
                            'code' => $userData['org_code']
                        ]
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Error in verifyOtp: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Refresh token
     */
    public function refreshToken()
    {
        // Get refresh token
        $rawBody = file_get_contents('php://input');
        $jsonData = json_decode($rawBody, true);
        
        $refreshToken = $_POST['refresh_token'] ?? $jsonData['refresh_token'] ?? null;
        
        if (empty($refreshToken)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => 'error',
                'message' => 'Refresh token es requerido'
            ]);
        }

        // Refrescar token
        $tokenModel = new UserApiTokenModel();
        $token = $tokenModel->refreshToken($refreshToken);

        if (!$token) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Refresh token inválido o expirado'
            ]);
        }

        // Responder con nuevo token
        return $this->response->setJSON([
            'status' => 'success',
            'token' => $token['accessToken'],
            'refresh_token' => $token['refreshToken'],
            'expires_at' => $token['expiresAt']
        ]);
    }

    /**
     * Logout (revoke token)
     */
    public function logout()
    {
        // Get token from header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = '';
        
        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
        }

        if (empty($token)) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'No se proporcionó token'
            ]);
        }

        // Revocar token
        $tokenModel = new UserApiTokenModel();
        $tokenModel->revokeToken($token);

        // Responder
        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }
    
    /**
     * Debug endpoint - used for testing API connectivity and route handling
     */
    public function debug()
    {
        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'API is working correctly',
            'data' => [
                'request_method' => $this->request->getMethod(),
                'request_headers' => $this->request->headers(),
                'request_body' => $this->request->getBody(),
                'server_time' => date('Y-m-d H:i:s'),
                'php_version' => phpversion(),
                'codeigniter_version' => \CodeIgniter\CodeIgniter::CI_VERSION
            ]
        ]);
    }
    
    /**
     * Test OTP without authentication - PUBLIC TESTING ONLY
     */
    public function testOtp()
    {
        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Test OTP endpoint',
            'data' => [
                'test_otp' => '123456',
                'expires_in' => '5 minutes'
            ]
        ]);
    }

    /**
     * Get authenticated user information
     * Used by mobile app
     */
    public function me()
    {
        // Get user from request (set by the apiAuth filter)
        $userData = $this->request->user ?? null;
        
        if (!$userData) {
            return $this->failUnauthorized('Not authenticated');
        }
        
        // Remove sensitive information
        unset($userData['password']);
        unset($userData['remember_token']);
        unset($userData['reset_token']);
        unset($userData['reset_token_expires_at']);
        
        // Get user's organization
        $orgModel = new \App\Models\OrganizationModel();
        $organization = null;
        
        if (!empty($userData['organization_id'])) {
            $organization = $orgModel->find($userData['organization_id']);
        }
        
        // Format the response
        $response = [
            'user' => $userData,
            'organization' => $organization
        ];
        
        return $this->respond([
            'success' => true,
            'data' => $response
        ]);
    }

    /**
     * Get user profile information
     * This endpoint can be used by the mobile app to get user information
     * after successful OTP verification
     */
    public function profile()
    {
        // Get token from Authorization header
        $authHeader = $this->request->getHeaderLine('Authorization');
        $token = null;
        
        if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }
        
        // If no token in header, try from query string
        if (!$token) {
            $token = $this->request->getGet('token');
        }
        
        // If still no token, check if we have a session token
        if (!$token && session()->has('token')) {
            $token = session()->get('token');
        }
        
        if (!$token) {
            return $this->respond([
                'success' => false,
                'message' => 'No authentication token provided',
                'data' => null
            ]);
        }
        
        // Validate token
        $tokenModel = new UserApiTokenModel();
        $tokenData = $tokenModel->getByToken($token);
        
        if (!$tokenData) {
            return $this->respond([
                'success' => false,
                'message' => 'Invalid token',
                'data' => null
            ]);
        }
        
        // Get user data
        $userModel = new UserModel();
        $userData = $userModel->find($tokenData['user_id']);
        
        if (!$userData) {
            return $this->respond([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ]);
        }
        
        // Remove sensitive information
        unset($userData['password']);
        unset($userData['remember_token']);
        unset($userData['reset_token']);
        unset($userData['reset_token_expires_at']);
        
        // Get user's organization
        $orgModel = new \App\Models\OrganizationModel();
        $organization = null;
        
        if (!empty($userData['organization_id'])) {
            $organization = $orgModel->find($userData['organization_id']);
        }
        
        // Format the response
        $response = [
            'user' => $userData,
            'organization' => $organization,
            'token' => $token
        ];
        
        return $this->respond([
            'success' => true,
            'message' => 'User profile retrieved successfully',
            'data' => $response
        ]);
    }
}