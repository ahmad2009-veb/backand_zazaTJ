<?php

namespace App\Services\Admin;

use App\CentralLogics\Helpers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    public function updateAdminProfile($admin, array $data): void
    {
        $admin->f_name = $data['f_name'];
        $admin->l_name = $data['l_name'] ?? $admin->l_name;
        $admin->email  = $data['email'] ?? $admin->email;
        $admin->phone  = $data['phone'] ?? $admin->phone;

        if (isset($data['password'])) {
            $admin->password = Hash::make($data['password']);
        }

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $admin->image = Helpers::update('admin/', $admin->image, 'png', $data['image']);
        }

        $admin->save();
    }
}
