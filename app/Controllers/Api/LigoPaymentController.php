<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class LigoPaymentController extends ResourceController
{
    use ResponseTrait;
    
    protected $invoiceModel;
    protected $organizationModel;
    protected $user;
    
    public function __construct()
    {
        $this->invoiceModel = new \App\Models\InvoiceModel();
        $this->organizationModel = new \App\Models\OrganizationModel();
        // User will be set by the auth filter
        $this->user = session()->get('api_user');
    }
    
    /**
     * Generate QR code for invoice payment via mobile app
     *
     * @param string $invoiceId
     * @return mixed
     */
    public function generateQR($invoiceId = null)
    {
        if (!$invoiceId) {
            return $this->failValidationErrors('Invoice ID is required');
        }
        
        // Get invoice details
        $invoice = $this->invoiceModel->find($invoiceId);
        
        if (!$invoice) {
            return $this->fail('Invoice not found', 404);
        }
        
        // Check if user has access to this invoice
        if (!$this->canAccessInvoice($invoice)) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        
        // Get organization details
        $organization = $this->organizationModel->find($invoice['organization_id']);
        
        if (!$organization) {
            return $this->fail('Organization not found', 404);
        }
        
        // Check if Ligo is enabled for this organization
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            return $this->fail('Ligo payments not enabled for this organization', 400);
        }
        
        // Check if Ligo credentials are configured
        if (empty($organization['ligo_username']) || empty($organization['ligo_password']) || empty($organization['ligo_company_id'])) {
            return $this->fail('Ligo API credentials not configured', 400);
        }
        
        // Prepare order data for Ligo
        $orderData = [
            'amount' => $invoice['amount'],
            'currency' => $invoice['currency'] ?? 'PEN',
            'orderId' => $invoice['id'],
            'description' => "Pago factura #{$invoice['invoice_number']}"
        ];
        
        // Create order in Ligo
        $response = $this->createLigoOrder($orderData, $organization);
        
        if (isset($response->error)) {
            return $this->fail($response->error, 400);
        }
        
        return $this->respond([
            'success' => true,
            'qr_data' => $response->qr_data ?? null,
            'qr_image_url' => $response->qr_image_url ?? null,
            'order_id' => $response->order_id ?? null,
            'expiration' => $response->expiration ?? null
        ]);
    }
    
    /**
     * Generate QR code for instalment payment via mobile app
     *
     * @param string $instalmentId
     * @return mixed
     */
    public function generateInstalmentQR($instalmentId = null)
    {
        log_message('info', 'LigoPaymentController::generateInstalmentQR called with instalmentId: ' . $instalmentId);
        
        if (!$instalmentId) {
            return $this->fail('Instalment ID is required', 400);
        }
        
        // Get instalment details
        $instalmentModel = new \App\Models\InstalmentModel();
        $instalment = $instalmentModel->find($instalmentId);
        
        if (!$instalment) {
            return $this->fail('Instalment not found', 404);
        }
        
        // Get invoice details
        $invoiceModel = new \App\Models\InvoiceModel();
        $invoice = $invoiceModel->find($instalment['invoice_id']);
        
        if (!$invoice) {
            return $this->fail('Invoice not found for this instalment', 404);
        }
        
        // Check if user has access to this invoice
        if (!$this->canAccessInvoice($invoice)) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        
        // Get organization details
        $organization = $this->organizationModel->find($invoice['organization_id']);
        
        if (!$organization) {
            return $this->fail('Organization not found', 404);
        }
        
        // Check if Ligo is enabled for this organization
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            return $this->fail('Ligo payments not enabled for this organization', 400);
        }
        
        // Check if Ligo credentials are configured
        if (empty($organization['ligo_username']) || empty($organization['ligo_password']) || empty($organization['ligo_company_id'])) {
            return $this->fail('Ligo API credentials not configured', 400);
        }
        
        // Get the invoice number from the appropriate field
        $invoiceNumber = $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A';
        
        // Prepare order data for Ligo
        $orderData = [
            'amount' => $instalment['amount'],
            'currency' => $invoice['currency'] ?? 'PEN',
            'orderId' => $instalment['id'],
            'description' => "Pago cuota #{$instalment['number']} de factura #{$invoiceNumber}",
            'qr_type' => 'dynamic'
        ];
        
        // Log the invoice and instalment data for debugging
        log_message('debug', 'Invoice data: ' . json_encode($invoice));
        log_message('debug', 'Instalment data: ' . json_encode($instalment));
        log_message('debug', 'Order data for QR generation: ' . json_encode($orderData));
        
        // Get auth token
        $authToken = $this->getAuthToken($organization);
        
        if (isset($authToken->error)) {
            return $this->fail($authToken->error, 400);
        }
        
        // URL para generar QR según la documentación
        $prefix = 'dev'; // Cambiar a 'dev' para entorno de desarrollo
        $url = "https://cce-api-gateway-{$prefix}.ligocloud.tech/v1/createQr";
        
        // Asegurar que tenemos valores válidos para los campos requeridos
        $idCuenta = !empty($organization['ligo_account_id']) ? $organization['ligo_account_id'] : '92100178794744781044';
        $codigoComerciante = !empty($organization['ligo_merchant_code']) ? $organization['ligo_merchant_code'] : '4829';
        
        // Preparar datos para la generación de QR según la documentación
        $qrData = [
            'header' => [
                'sisOrigen' => '0921'
            ],
            'data' => [
                'qrTipo' => '12', // QR dinámico con monto
                'idCuenta' => $idCuenta,
                'moneda' => $orderData['currency'] == 'PEN' ? '604' : '840', // 604 = Soles, 840 = Dólares
                'importe' => (int)($orderData['amount'] * 100), // Convertir a centavos
                'codigoComerciante' => $codigoComerciante,
                'nombreComerciante' => $organization['name'],
                'ciudadComerciante' => $organization['city'] ?? 'Lima',
                'glosa' => $orderData['description'],
                'info' => [
                    [
                        'codigo' => 'instalment_id',
                        'valor' => $instalment['id']
                    ],
                    [
                        'codigo' => 'invoice_id',
                        'valor' => $invoice['id']
                    ],
                    [
                        'codigo' => 'nombreCliente',
                        'valor' => $organization['name'] ?? 'Cliente'
                    ],
                    [
                        'codigo' => 'documentoIdentidad',
                        'valor' => $organization['tax_id'] ?? '00000000'
                    ]
                ]
            ],
            'type' => 'TEXT'
        ];
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($qrData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $authToken->token,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        
        // Registrar información detallada de la solicitud y respuesta
        log_message('debug', 'Solicitud a Ligo - URL: ' . $url);
        log_message('debug', 'Solicitud a Ligo - Datos: ' . json_encode($qrData));
        log_message('debug', 'Solicitud a Ligo - Headers: Authorization Bearer ' . substr($authToken->token, 0, 20) . '...');
        log_message('debug', 'Respuesta de Ligo - HTTP Code: ' . $info['http_code']);
        
        curl_close($curl);
        
        if ($err) {
            log_message('error', 'Ligo API Error: ' . $err);
            return $this->fail('Failed to connect to Ligo API: ' . $err, 400);
        }
        
        $decoded = json_decode($response);
        
        if (!$decoded || !isset($decoded->data) || !isset($decoded->data->id)) {
            log_message('error', 'Invalid response from Ligo API: ' . $response);
            return $this->fail('Invalid response from Ligo API', 400);
        }
        
        // Crear objeto de respuesta con formato estandarizado
        $qrData = json_encode([
            'id' => $decoded->data->id,
            'amount' => $orderData['amount'],
            'currency' => $orderData['currency'],
            'description' => $orderData['description'],
            'merchant' => $organization['name'],
            'timestamp' => time(),
            'hash' => 'LIGO-' . $decoded->data->id,
            'instalment_id' => $instalment['id'],
            'invoice_id' => $invoice['id']
        ]);
        
        $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qrData);
        
        return $this->respond([
            'success' => true,
            'qr_data' => $qrData,
            'qr_image_url' => $qrImageUrl,
            'order_id' => $decoded->data->id,
            'instalment_id' => $instalment['id'],
            'invoice_id' => $invoice['id']
        ]);
    }
    
    /**
     * Generate static QR code for organization via mobile app
     * This QR code doesn't have a predefined amount and can be used for multiple payments
     *
     * @param string $organizationId
     * @return mixed
     */
    public function generateStaticQR($organizationId = null)
    {
        if (!$organizationId) {
            return $this->failValidationErrors('Organization ID is required');
        }
        
        // Get organization details
        $organization = $this->organizationModel->find($organizationId);
        
        if (!$organization) {
            // Try to find by UUID
            $organization = $this->organizationModel->where('uuid', $organizationId)->first();
            
            if (!$organization) {
                return $this->fail('Organization not found', 404);
            }
        }
        
        // Check if user has access to this organization
        if (!$this->canAccessOrganization($organization)) {
            return $this->failForbidden('You do not have access to this organization');
        }
        
        // Check if Ligo is enabled for this organization
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            return $this->fail('Ligo payments not enabled for this organization', 400);
        }
        
        // Check if Ligo credentials are configured
        if (empty($organization['ligo_username']) || empty($organization['ligo_password']) || empty($organization['ligo_company_id'])) {
            return $this->fail('Ligo API credentials not configured', 400);
        }
        
        // Prepare order data for Ligo
        $orderData = [
            'amount' => null, // No amount for static QR
            'currency' => 'PEN',
            'orderId' => 'static-' . $organization['id'] . '-' . time(),
            'description' => 'QR Estático para ' . $organization['name'],
            'qr_type' => 'static'
        ];
        
        // Create order in Ligo
        $response = $this->createLigoOrder($orderData, $organization);
        
        if (isset($response->error)) {
            return $this->fail($response->error, 400);
        }
        
        return $this->respond([
            'success' => true,
            'organization_name' => $organization['name'],
            'qr_data' => $response->qr_data ?? null,
            'qr_image_url' => $response->qr_image_url ?? null,
            'order_id' => $response->order_id ?? null
        ]);
    }
    
    /**
     * Check if user can access organization
     *
     * @param array $organization
     * @return bool
     */
    private function canAccessOrganization($organization)
    {
        // Superadmin can access any organization
        if ($this->user['role'] === 'superadmin') {
            return true;
        }
        
        // Admin can access their own organization
        if ($this->user['role'] === 'admin' && $this->user['organization_id'] == $organization['id']) {
            return true;
        }
        
        // Regular users can access their organization
        if ($this->user['organization_id'] == $organization['id']) {
            return true;
        }
        
        return false;
    }
    

    
    /**
     * Create order in Ligo API
     *
     * @param array $data Order data
     * @param array $organization Organization with Ligo credentials
     * @return object Response from Ligo API
     */
    private function createLigoOrder($data, $organization)
    {
        $curl = curl_init();
        
        // Obtener token de autenticación si no existe o está vencido
        $authToken = $this->getAuthToken($organization);
        
        if (isset($authToken->error)) {
            return (object)['error' => $authToken->error];
        }
        
        // URL para generar QR según la documentación
        $prefix = 'dev'; // Cambiar a 'dev' para entorno de desarrollo
        $url = "https://cce-api-gateway-{$prefix}.ligocloud.tech/v1/createQr";
        
        // Asegurar que tenemos valores válidos para los campos requeridos
        $idCuenta = !empty($organization['ligo_account_id']) ? $organization['ligo_account_id'] : '92100178794744781044';
        $codigoComerciante = !empty($organization['ligo_merchant_code']) ? $organization['ligo_merchant_code'] : '4829';
        
        // Determinar el tipo de QR a generar (estático o dinámico)
        $qrTipo = isset($data['qr_type']) && $data['qr_type'] === 'static' ? '11' : '12';
        
        // Preparar datos para la generación de QR según la documentación
        $qrData = [
            'header' => [
                'sisOrigen' => '0921'
            ],
            'data' => [
                'qrTipo' => $qrTipo, // 11 = Estático, 12 = Dinámico con monto
                'idCuenta' => $idCuenta,
                'moneda' => $data['currency'] == 'PEN' ? '604' : '840', // 604 = Soles, 840 = Dólares
                'codigoComerciante' => $codigoComerciante,
                'nombreComerciante' => $organization['name'],
                'ciudadComerciante' => $organization['city'] ?? 'Lima'
            ],
            'type' => 'TEXT'
        ];
        
        // Agregar campos adicionales para QR dinámico
        if ($qrTipo === '12') {
            $qrData['data']['importe'] = (int)($data['amount'] * 100); // Convertir a centavos
            $qrData['data']['glosa'] = $data['description'];
            $qrData['data']['info'] = [
                [
                    'codigo' => 'invoice_id',
                    'valor' => $data['orderId']
                ],
                [
                    'codigo' => 'nombreCliente',
                    'valor' => $organization['name'] ?? 'Cliente'
                ],
                [
                    'codigo' => 'documentoIdentidad',
                    'valor' => $organization['tax_id'] ?? '00000000'
                ]
            ];
        } else {
            // Para QR estático estos campos son null
            $qrData['data']['importe'] = null;
            $qrData['data']['glosa'] = null;
            $qrData['data']['info'] = null;
        }
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($qrData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $authToken->token,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYHOST => 0, // Deshabilitar verificación de host SSL
            CURLOPT_SSL_VERIFYPEER => false, // Deshabilitar verificación de certificado SSL
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        
        // Registrar información detallada de la solicitud y respuesta
        log_message('debug', 'Solicitud a Ligo - URL: ' . $url);
        log_message('debug', 'Solicitud a Ligo - Datos: ' . json_encode($qrData));
        log_message('debug', 'Solicitud a Ligo - Headers: Authorization Bearer ' . substr($authToken->token, 0, 20) . '...');
        log_message('debug', 'Respuesta de Ligo - HTTP Code: ' . $info['http_code']);
        
        curl_close($curl);
        
        if ($err) {
            log_message('error', 'Ligo API Error: ' . $err);
            return (object)['error' => 'Failed to connect to Ligo API: ' . $err];
        }
        
        $decoded = json_decode($response);
        
        if (!$decoded || !isset($decoded->data) || !isset($decoded->data->id)) {
            log_message('error', 'Invalid response from Ligo API: ' . $response);
            return (object)['error' => 'Invalid response from Ligo API'];
        }
        
        // Crear objeto de respuesta con formato estandarizado
        $result = new \stdClass();
        $result->qr_data = json_encode([
            'id' => $decoded->data->id,
            'amount' => $qrTipo === '12' ? $data['amount'] : null,
            'currency' => $data['currency'] ?? 'PEN',
            'description' => $data['description'] ?? null,
            'merchant' => $organization['name'],
            'timestamp' => time(),
            'hash' => 'LIGO-' . $decoded->data->id
        ]);
        $result->qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($result->qr_data);
        $result->order_id = $decoded->data->id;
        
        return $result;
    }
    
    /**
     * Get authentication token for Ligo API
     * 
     * @param array $organization Organization with Ligo credentials
     * @return object Token object with token and expiry or error
     */
    private function getAuthToken($organization)
    {
        // Check if token exists and is not expired
        if (!empty($organization['ligo_token']) && !empty($organization['ligo_token_expiry'])) {
            $tokenExpiry = strtotime($organization['ligo_token_expiry']);
            if ($tokenExpiry > time()) {
                // Token is still valid
                $token = new \stdClass();
                $token->token = $organization['ligo_token'];
                $token->expiry = $organization['ligo_token_expiry'];
                return $token;
            }
        }
        
        // Token doesn't exist or is expired, get a new one
        try {
            // Verificar credenciales
            if (empty($organization['ligo_company_id'])) {
                log_message('error', 'API: Credenciales de Ligo incompletas para la organización ID: ' . $organization['id']);
                return (object)['error' => 'Incomplete Ligo credentials'];
            }
            
            // Definir URLs de API
            if (empty($organization['ligo_api_url'])) {
                // URL por defecto para el entorno de desarrollo
                $organization['ligo_api_url'] = 'https://cce-api-gateway-dev.ligocloud.tech/v1';
                log_message('info', 'API: Usando URL de API por defecto: ' . $organization['ligo_api_url']);
            }
            
            // URL específica para autenticación
            $authUrl = 'https://cce-auth-dev.ligocloud.tech/v1/auth/sign-in?companyId=' . $organization['ligo_company_id'];
            log_message('info', 'API: Usando URL de autenticación: ' . $authUrl);
            
            // Intentar generar el token JWT usando la clave privada
            try {
                // Verificar que la clave privada exista
                if (empty($organization['ligo_private_key'])) {
                    log_message('error', 'API: Clave privada de Ligo no configurada para la organización ID: ' . $organization['id']);
                    return (object)['error' => 'Ligo private key not configured'];
                }
                
                // Cargar la clase JwtGenerator
                $privateKey = $organization['ligo_private_key'];
                $formattedKey = \App\Libraries\JwtGenerator::formatPrivateKey($privateKey);
                
                // Preparar payload
                $payload = [
                    'companyId' => $organization['ligo_company_id']
                ];
                
                // Generar token JWT
                $authorizationToken = \App\Libraries\JwtGenerator::generateToken($payload, $formattedKey, [
                    'issuer' => 'ligo',
                    'audience' => 'ligo-calidad.com',
                    'subject' => 'ligo@gmail.com',
                    'expiresIn' => 3600 // 1 hora
                ]);
                
                log_message('info', 'API: Token JWT generado correctamente');
                log_message('debug', 'API: Token JWT: ' . substr($authorizationToken, 0, 30) . '...');
            } catch (\Exception $e) {
                log_message('error', 'API: Error al generar token JWT: ' . $e->getMessage());
                return (object)['error' => 'Error al generar token JWT: ' . $e->getMessage()];
            }
            
            $companyId = $organization['ligo_company_id'];
            // Usar la URL específica para autenticación
            $url = $authUrl;
            
            $curl = curl_init();
            
            // Datos de autenticación para la solicitud POST
            $authData = [
                'username' => $organization['ligo_username'] ?? '',
                'password' => $organization['ligo_password'] ?? ''
            ];
            $requestBody = json_encode($authData);
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $requestBody,  // Agregar datos de autenticación
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $authorizationToken,
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($requestBody)  // Agregar Content-Length
                ],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $info = curl_getinfo($curl);
            
            curl_close($curl);
            
            if ($err) {
                log_message('error', 'API: Error en solicitud de autenticación: ' . $err);
                return (object)['error' => 'Error en solicitud de autenticación: ' . $err];
            }
            
            if ($info['http_code'] != 200) {
                log_message('error', 'API: Error en autenticación. HTTP Code: ' . $info['http_code'] . ' - Respuesta: ' . $response);
                return (object)['error' => 'Error en autenticación. HTTP Code: ' . $info['http_code']];
            }
            
            $decoded = json_decode($response);
            
            // Verificar si hay token en la respuesta
            if (!$decoded || !isset($decoded->data) || !isset($decoded->data->access_token)) {
                log_message('error', 'API: No se recibió token en la respuesta: ' . json_encode($decoded));
                
                // Extraer mensaje de error
                $errorMsg = 'No token in auth response';
                if (isset($decoded->message)) {
                    $errorMsg .= ': ' . $decoded->message;
                } elseif (isset($decoded->errors)) {
                    $errorMsg .= ': ' . (is_string($decoded->errors) ? $decoded->errors : json_encode($decoded->errors));
                } elseif (isset($decoded->error)) {
                    $errorMsg .= ': ' . (is_string($decoded->error) ? $decoded->error : json_encode($decoded->error));
                }
                
                return (object)['error' => $errorMsg];
            }
            
            log_message('info', 'API: Autenticación con Ligo exitosa, token obtenido');
            log_message('debug', 'API: Token de acceso recibido: ' . substr($decoded->data->access_token, 0, 30) . '...');
            
            // Guardar el token en la base de datos para futuros usos
            try {
                // Calcular fecha de expiración
                $expiryDate = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $this->organizationModel->update($organization['id'], [
                    'ligo_token' => $decoded->data->access_token,
                    'ligo_token_expiry' => $expiryDate,
                    'ligo_auth_error' => null,
                    'ligo_enabled' => 1 // Habilitar Ligo automáticamente si la autenticación es exitosa
                ]);
                
                log_message('info', 'API: Token guardado en la base de datos con expiración: ' . $expiryDate);
            } catch (\Exception $e) {
                log_message('error', 'API: Error al guardar token en la base de datos: ' . $e->getMessage());
                // Continuar aunque no se pueda guardar el token
            }
            
            // Create token object
            $token = new \stdClass();
            $token->token = $decoded->data->access_token;
            $token->expiry = $expiryDate;
            
            return $token;
            
        } catch (\Exception $e) {
            log_message('error', 'API: Excepción en autenticación Ligo: ' . $e->getMessage());
            return (object)['error' => 'Error interno: ' . $e->getMessage()];
        }
    }
    
    /**
     * Check if user can access an invoice
     * 
     * @param array $invoice
     * @return bool
     */
    private function canAccessInvoice($invoice)
    {
        // If user is not set (mobile app might be using token auth without session)
        if (!$this->user) {
            // For mobile app requests, we'll allow access if the request has a valid token
            // The apiAuth filter has already validated the token at this point
            log_message('debug', 'canAccessInvoice: User not found in session, but token is valid. Allowing access.');            
            return true;
        }
        
        // Superadmin can access any invoice
        if (isset($this->user['role']) && $this->user['role'] === 'superadmin') {
            return true;
        }
        
        // Admin can access invoices from their organization
        if (isset($this->user['role']) && $this->user['role'] === 'admin' && 
            isset($this->user['organization_id']) && $this->user['organization_id'] == $invoice['organization_id']) {
            return true;
        }
        
        // For regular users, check if they have access to the client through portfolios
        if (isset($this->user['role']) && $this->user['role'] === 'user') {
            $portfolioModel = new \App\Models\PortfolioModel();
            $portfolios = $portfolioModel->getByUser($this->user['id']);
            
            // Get all client IDs from user's portfolios
            $clientIds = [];
            foreach ($portfolios as $portfolio) {
                $clients = $portfolioModel->getAssignedClients($portfolio['id']);
                foreach ($clients as $client) {
                    $clientIds[] = $client['id'];
                }
            }
            
            return in_array($invoice['client_id'], $clientIds);
        }
        
        return false;
    }
}
