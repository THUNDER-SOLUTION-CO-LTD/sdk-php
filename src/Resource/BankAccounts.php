<?php

declare(strict_types=1);

namespace Thunder\Resource;

use Thunder\Input\{CreateBankAccountParams, ListBankAccountsParams, UpdateBankAccountParams, Validation};
use Thunder\Model\{BankAccount, BankAccountPage, LinkResult, UnlinkResult};
use Thunder\Transport\Api;

final readonly class BankAccounts
{
    /** Create the account resource with a shared transport. */
    public function __construct(private Api $api) {}
    /** List accounts linked to this application. */
    public function list(?ListBankAccountsParams $params = null): BankAccountPage
    {
        return $this->api->call(BankAccountPage::class, 'GET', '/bank-accounts', query: $params?->jsonSerialize() ?? [], safe: true);
    }
    /** List accessible accounts, including unlinked accounts. */
    public function listAll(?ListBankAccountsParams $params = null): BankAccountPage
    {
        return $this->api->call(BankAccountPage::class, 'GET', '/bank-accounts/all', query: $params?->jsonSerialize() ?? [], safe: true);
    }
    /** Read one account by its positive integer ID. */
    public function get(int $id): BankAccount
    {
        Validation::id($id);
        return $this->api->call(BankAccount::class, 'GET', '/bank-accounts/' . $id, safe: true);
    }
    /** Create a receiving account. */
    public function create(CreateBankAccountParams $params): BankAccount
    {
        return $this->api->call(BankAccount::class, 'POST', '/bank-accounts', $params->jsonSerialize());
    }
    /** Update only supplied fields; explicit null clears a nullable field. */
    public function update(int $id, UpdateBankAccountParams $params): BankAccount
    {
        Validation::id($id);
        return $this->api->call(BankAccount::class, 'PATCH', '/bank-accounts/' . $id, $params->jsonSerialize());
    }
    /** Link an existing account to this application. */
    public function link(int $id): LinkResult
    {
        Validation::id($id);
        return $this->api->call(LinkResult::class, 'POST', '/bank-accounts/' . $id . '/link', safe: true);
    }
    /** Remove the application link without deleting the account. */
    public function unlink(int $id): UnlinkResult
    {
        Validation::id($id);
        return $this->api->call(UnlinkResult::class, 'DELETE', '/bank-accounts/' . $id . '/link');
    }
    /** @return \Generator<int, BankAccount> */
    public function iterate(?ListBankAccountsParams $params = null): \Generator
    {
        yield from $this->pages($params, false);
    }
    /** @return \Generator<int, BankAccount> */
    public function iterateAll(?ListBankAccountsParams $params = null): \Generator
    {
        yield from $this->pages($params, true);
    }
    /** @return \Generator<int, BankAccount> */
    private function pages(?ListBankAccountsParams $params, bool $all): \Generator
    {
        $page = $params->page ?? 1;
        $limit = $params->limit ?? 100;
        do {
            $query = new ListBankAccountsParams($params?->type, $params?->bankCode, $page, $limit);
            $result = $all ? $this->listAll($query) : $this->list($query);
            yield from $result->items;
            if ($result->items === [] || $page * $limit >= $result->total) {
                return;
            }
            ++$page;
        } while (true);
    }
}
