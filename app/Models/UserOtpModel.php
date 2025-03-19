<?php

namespace App\Models;

use CodeIgniter\Model;

class UserOtpModel extends Model
{
    protected $table            = 'user_otp_codes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id', 'code', 'device_info', 'expires_at', 'used_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $deletedField  = '';

    // Validation
    protected $validationRules      = [
        'user_id'    => 'required|is_natural_no_zero',
        'code'       => 'required|min_length[4]|max_length[10]',
        'expires_at' => 'required',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;
    
    /**
     * Generate a new OTP code for a user
     */
    public function generateOTP($userId, $deviceInfo = null, $expiresInMinutes = 15, $codeLength = 6)
    {
        // Invalidate all previous OTPs for this user
        $this->where('user_id', $userId)
             ->where('used_at IS NULL')
             ->set(['used_at' => date('Y-m-d H:i:s')])
             ->update();
        
        // Generate a random numeric code
        $code = '';
        for ($i = 0; $i < $codeLength; $i++) {
            $code .= mt_rand(0, 9);
        }
        
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInMinutes} minutes"));
        
        $data = [
            'user_id'    => $userId,
            'code'       => $code,
            'device_info' => $deviceInfo,
            'expires_at' => $expiresAt,
        ];
        
        $this->insert($data);
        
        return $code;
    }
    
    /**
     * Verify an OTP code
     */
    public function verifyOTP($userId, $code)
    {
        $otp = $this->where('user_id', $userId)
                    ->where('code', $code)
                    ->where('used_at IS NULL')
                    ->where('expires_at >', date('Y-m-d H:i:s'))
                    ->first();
        
        if (!$otp) {
            return false;
        }
        
        // Mark as used
        $this->update($otp['id'], ['used_at' => date('Y-m-d H:i:s')]);
        
        return true;
    }
    
    /**
     * Clean up expired OTP codes
     */
    public function cleanExpired()
    {
        return $this->where('expires_at <', date('Y-m-d H:i:s'))
                    ->where('used_at IS NULL')
                    ->delete();
    }
}