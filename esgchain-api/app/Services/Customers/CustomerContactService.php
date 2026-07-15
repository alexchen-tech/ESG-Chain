<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerContact;

class CustomerContactService
{
    public function addContact(Customer $customer, array $data): CustomerContact
    {
        // is_primary 互斥：先取消現有主要聯絡人
        if (!empty($data['is_primary'])) {
            $customer->contacts()->where('is_primary', true)->update(['is_primary' => false]);
        }

        return $customer->contacts()->create($data);
    }

    public function removeContact(CustomerContact $contact): void
    {
        $contact->delete();
    }
}
