<?php

namespace RankSavvy\Modules\Social;

use RankSavvy\Core\Security\KeyVault;

class SocialAccountManager
{
    private const OPTION_KEY = 'ranksavvy_social_accounts';

    public function getAccounts(): array
    {
        $accounts = get_option(self::OPTION_KEY, []);
        return is_array($accounts) ? $accounts : [];
    }

    public function getAccountsByNetwork(string $network): array
    {
        return array_filter($this->getAccounts(), function ($account) use ($network) {
            return $account['network'] === $network;
        });
    }

    public function saveAccount(array $accountData): void
    {
        $accounts = $this->getAccounts();
        
        // Use a simple ID generation based on network and profile ID or random
        $accountId = $accountData['id'] ?? uniqid($accountData['network'] . '_');
        $accountData['id'] = $accountId;

        if (!empty($accountData['access_token'])) {
            $accountData['access_token'] = KeyVault::encrypt($accountData['access_token']);
        }

        // Check if exists, update or append
        $exists = false;
        foreach ($accounts as $index => $acc) {
            if ($acc['id'] === $accountId) {
                $accounts[$index] = array_merge($acc, $accountData);
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $accounts[] = $accountData;
        }

        update_option(self::OPTION_KEY, $accounts);
    }

    public function deleteAccount(string $accountId): bool
    {
        $accounts = $this->getAccounts();
        $initialCount = count($accounts);

        $accounts = array_filter($accounts, function ($account) use ($accountId) {
            return $account['id'] !== $accountId;
        });

        if (count($accounts) !== $initialCount) {
            update_option(self::OPTION_KEY, array_values($accounts));
            return true;
        }

        return false;
    }

    public function updateAccount(string $accountId, array $data): bool
    {
        $accounts = $this->getAccounts();
        $updated = false;

        foreach ($accounts as $index => $acc) {
            if ($acc['id'] === $accountId) {
                // Ensure we don't overwrite id, network, or access_token with empty values
                unset($data['id'], $data['network'], $data['access_token']);
                $accounts[$index] = array_merge($acc, $data);
                $updated = true;
                break;
            }
        }

        if ($updated) {
            update_option(self::OPTION_KEY, $accounts);
            return true;
        }

        return false;
    }
}
