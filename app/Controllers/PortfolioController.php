<?php

namespace App\Controllers;

use App\Models\PortfolioModel;
use App\Models\ClientModel;
use App\Models\UserModel;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;

class PortfolioController extends BaseController
{
    use OrganizationTrait;
    
    protected $auth;
    protected $session;
    protected $db;
    
    public function __construct()
    {
        $this->auth = new Auth();
        $this->session = \Config\Services::session();
        $this->db = \Config\Database::connect();
        helper(['form', 'url', 'uuid']);
    }
    
    public function index()
    {
        log_message('debug', '====== PORTFOLIOS INDEX ======');
        
        // Refresh organization context from session
        $currentOrgId = $this->refreshOrganizationContext();
        
        $portfolioModel = new PortfolioModel();
        $auth = $this->auth;
        
        // Filter portfolios based on role
        if ($auth->hasRole('superadmin')) {
            // Superadmin can see all portfolios or filter by organization
            if ($currentOrgId) {
                // Use the trait method to apply organization filter
                $this->applyOrganizationFilter($portfolioModel, $currentOrgId);
                $portfolios = $portfolioModel->findAll();
                
                // Verify filtering is working
                log_message('debug', 'SQL Query: ' . $portfolioModel->getLastQuery()->getQuery());
                log_message('debug', 'Superadmin fetched ' . count($portfolios) . ' portfolios for organization ' . $currentOrgId);
            } else {
                $portfolios = $portfolioModel->findAll();
                log_message('debug', 'Superadmin fetched all ' . count($portfolios) . ' portfolios (no org filter)');
            }
        } else if ($auth->hasRole('admin')) {
            // Admin can see all portfolios from their organization
            $adminOrgId = $auth->user()['organization_id']; // Always use admin's fixed organization
            $portfolios = $portfolioModel->where('organization_id', $adminOrgId)->findAll();
            log_message('debug', 'Admin fetched ' . count($portfolios) . ' portfolios for organization ' . $adminOrgId);
        } else {
            // Regular users can only see their assigned portfolios
            $portfolios = $portfolioModel->getByUser($auth->user()['id']);
            log_message('debug', 'User has ' . count($portfolios) . ' portfolios');
        }
        
        // If no portfolios found with role-based filtering, log this fact
        if (empty($portfolios)) {
            $allPortfolios = $portfolioModel->findAll();
            log_message('debug', 'No portfolios found with filtering. Total portfolios in database: ' . count($allPortfolios));
            
            // For debugging, log all available organizations
            $orgs = $this->db->table('organizations')->get()->getResultArray();
            log_message('debug', 'Available organizations: ' . json_encode(array_column($orgs, 'id')));
        }
        
        // Get organization names for portfolios
        $organizationModel = new \App\Models\OrganizationModel();
        $organizationsById = [];
        foreach ($organizationModel->findAll() as $org) {
            $organizationsById[$org['id']] = $org;
        }
        
        // Initialize view data
        $data = [
            'portfolios' => $portfolios,
            'organizations' => $organizationsById,
        ];
        
        // Use the trait to prepare organization-related data for the view
        $data = $this->prepareOrganizationData($data);
        
        return view('portfolios/index', $data);
    }
    
    public function create()
    {
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para crear carteras.');
        }
        
        $data = [
            'auth' => $this->auth,
        ];
        
        // Get organization ID
        $organizationId = $this->auth->hasRole('superadmin') ? null : $this->auth->organizationId();
        
        if ($this->auth->hasRole('superadmin')) {
            $organizationModel = new \App\Models\OrganizationModel();
            $data['organizations'] = $organizationModel->findAll();
        }
        
        // Get available users and clients if organization is selected
        if ($organizationId || $this->request->getPost('organization_id')) {
            $portfolioModel = new PortfolioModel();
            $targetOrgId = $organizationId ?: $this->request->getPost('organization_id');
            
            $data['users'] = $portfolioModel->getAvailableUsers($targetOrgId);
            $data['clients'] = $portfolioModel->getAvailableClients($targetOrgId);
        }
        
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name' => 'required|min_length[3]|max_length[100]',
                'description' => 'permit_empty',
                'status' => 'required|in_list[active,inactive]',
                'user_id' => 'required|is_not_unique[users.uuid]'
            ];
            
            if ($this->auth->hasRole('superadmin')) {
                $rules['organization_id'] = 'required|is_natural_no_zero';
            }
            
            if ($this->validate($rules)) {
                $portfolioModel = new PortfolioModel();
                
                $organizationId = $this->auth->hasRole('superadmin')
                    ? $this->request->getPost('organization_id')
                    : $this->auth->organizationId();
                
                helper('uuid');
                $uuid = substr(generate_uuid(), 0, 8);
                
                $data = [
                    'uuid' => $uuid,
                    'organization_id' => $organizationId,
                    'name' => $this->request->getPost('name'),
                    'description' => $this->request->getPost('description'),
                    'status' => $this->request->getPost('status'),
                ];
                
                $portfolioId = $portfolioModel->insert($data);
                
                if ($portfolioId) {
                    // Assign single user
                    $userId = $this->request->getPost('user_id');
                    if ($userId) {
                        $portfolioModel->assignUsers($uuid, [$userId]);
                    }
                    
                    // Assign selected clients
                    $clientIds = $this->request->getPost('client_ids') ?: [];
                    if (!empty($clientIds)) {
                        $portfolioModel->assignClients($uuid, $clientIds);
                    }
                    
                    return redirect()->to('/portfolios')->with('message', 'Cartera creada exitosamente.');
                }
                
                return redirect()->back()->withInput()->with('error', 'Error al crear la cartera.');
            }
            
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        
        return view('portfolios/create', $data);
    }

    public function view($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/portfolios')->with('error', 'UUID de cartera no proporcionado.');
        }

        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->where('uuid', $uuid)->first();

        if (!$portfolio) {
            return redirect()->to('/portfolios')->with('error', 'Cartera no encontrada.');
        }

        // Verificar permisos de organización
        if (!$this->auth->hasRole('superadmin') && $portfolio['organization_id'] !== $this->auth->organizationId()) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para ver esta cartera.');
        }

        // Obtener usuarios y clientes asignados
        $assignedUsers = $portfolioModel->getAssignedUsers($portfolio['uuid']);
        $assignedClients = $portfolioModel->getAssignedClients($portfolio['uuid']);

        $data = [
            'portfolio' => $portfolio,
            'assignedUsers' => $assignedUsers,
            'assignedClients' => $assignedClients,
            'auth' => $this->auth,
            'request' => $this->request
        ];

        return view('portfolios/view', $data);
    }
    
    public function edit($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/portfolios')->with('error', 'UUID de cartera no proporcionado.');
        }

        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para editar carteras.');
        }

        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->where('uuid', $uuid)->first();

        if (!$portfolio) {
            return redirect()->to('/portfolios')->with('error', 'Cartera no encontrada.');
        }

        // Verificar permisos de organización
        if (!$this->auth->hasRole('superadmin') && $portfolio['organization_id'] !== $this->auth->organizationId()) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para editar esta cartera.');
        }

        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name' => 'required|min_length[3]|max_length[100]',
                'description' => 'permit_empty',
                'status' => 'required|in_list[active,inactive]',
                'user_id' => 'required|is_not_unique[users.uuid]'
            ];

            if ($this->validate($rules)) {
                $data = [
                    'name' => $this->request->getPost('name'),
                    'description' => $this->request->getPost('description'),
                    'status' => $this->request->getPost('status'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                if ($portfolioModel->update($portfolio['id'], $data)) {
                    // Actualizar usuario asignado
                    $userUuid = $this->request->getPost('user_id');
                    $portfolioModel->assignUsers($uuid, [$userUuid]);

                    // Actualizar clientes asignados
                    $clientUuids = $this->request->getPost('client_ids') ?: [];
                    $portfolioModel->assignClients($uuid, $clientUuids);

                    return redirect()->to('/portfolios')->with('message', 'Cartera actualizada exitosamente.');
                }

                return redirect()->back()->withInput()->with('error', 'Error al actualizar la cartera.');
            }

            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Obtener usuario y clientes asignados actualmente
        $assignedUser = $portfolioModel->getAssignedUsers($uuid);
        $assignedUserId = !empty($assignedUser) ? $assignedUser[0]['uuid'] : null;

        $assignedClients = $portfolioModel->getAssignedClients($uuid);
        $assignedClientIds = array_column($assignedClients, 'uuid');

        // Obtener usuarios y clientes disponibles
        $organizationId = $portfolio['organization_id'];
        
        // Para la edición, incluimos el usuario actualmente asignado en la lista de disponibles
        $availableUsers = $portfolioModel->getAvailableUsers($organizationId);
        if ($assignedUserId) {
            $currentUser = $this->db->table('users')
                                  ->where('uuid', $assignedUserId)
                                  ->get()
                                  ->getRowArray();
            if ($currentUser) {
                $availableUsers[] = $currentUser;
            }
        }

        // Para los clientes, incluimos tanto los disponibles como los ya asignados
        $availableClients = array_merge(
            $portfolioModel->getAvailableClients($organizationId),
            $assignedClients
        );

        $data = [
            'portfolio' => $portfolio,
            'users' => $availableUsers,
            'clients' => $availableClients,
            'assigned_user_id' => $assignedUserId,
            'assigned_client_ids' => $assignedClientIds,
            'auth' => $this->auth
        ];

        return view('portfolios/edit', $data);
    }

    public function delete($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/portfolios')->with('error', 'UUID de cartera no proporcionado.');
        }

        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para eliminar carteras.');
        }

        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->where('uuid', $uuid)->first();

        if (!$portfolio) {
            return redirect()->to('/portfolios')->with('error', 'Cartera no encontrada.');
        }

        // Verificar permisos de organización
        if (!$this->auth->hasRole('superadmin') && $portfolio['organization_id'] !== $this->auth->organizationId()) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para eliminar esta cartera.');
        }

        if ($portfolioModel->delete($portfolio['id'])) {
            return redirect()->to('/portfolios')->with('message', 'Cartera eliminada exitosamente.');
        }

        return redirect()->to('/portfolios')->with('error', 'Error al eliminar la cartera.');
    }
    
    /**
     * Get available users by organization
     */
    public function getUsersByOrganization($organizationId)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid request']);
        }

        $portfolioModel = new PortfolioModel();
        $users = $portfolioModel->getAvailableUsers($organizationId);

        return $this->response->setJSON(['users' => $users]);
    }

    /**
     * Get available clients by organization
     */
    public function getClientsByOrganization($organizationId)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid request']);
        }

        $portfolioModel = new PortfolioModel();
        $clients = $portfolioModel->getAvailableClients($organizationId);

        return $this->response->setJSON(['clients' => $clients]);
    }
}
