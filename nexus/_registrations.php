<?php
declare(strict_types=1);

/**
 * Shared list contract for the admin registrations module.
 *
 * The on-screen list (registrations.php) and the CSV export
 * (registrations_export.php) both read their filters, sort order and page
 * size through here, so the export can never drift away from what the
 * administrator is looking at.
 */

/**
 * Sortable columns: request key => SQL column.
 * Everything is whitelisted because the ORDER BY clause cannot be bound.
 *
 * @return array<string, string>
 */
function bdst_reg_sort_columns(): array
{
    return [
        'name'     => 'full_name',
        'location' => 'district',
        'status'   => 'status',
        'date'     => 'submitted_at',
    ];
}

/**
 * Page sizes an administrator may pick from.
 *
 * @return list<int>
 */
function bdst_reg_per_page_options(): array
{
    return [15, 25, 50, 100];
}

/**
 * Validate + normalise the list query string.
 *
 * @param array<string, mixed> $query usually $_GET
 * @return array{q: string, status: string, sort: string, dir: string, per: int, page: int}
 */
function bdst_reg_read_query(array $query): array
{
    $search = trim((string) ($query['q'] ?? ''));
    if (mb_strlen($search) > 120) {
        $search = mb_substr($search, 0, 120);
    }

    $status = (string) ($query['status'] ?? '');
    if (!in_array($status, bdst_registration_statuses(), true)) {
        $status = '';
    }

    $sort = (string) ($query['sort'] ?? 'date');
    if (!array_key_exists($sort, bdst_reg_sort_columns())) {
        $sort = 'date';
    }

    $dir = strtolower((string) ($query['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

    $per = (int) ($query['per'] ?? 15);
    if (!in_array($per, bdst_reg_per_page_options(), true)) {
        $per = 15;
    }

    return [
        'q'      => $search,
        'status' => $status,
        'sort'   => $sort,
        'dir'    => $dir,
        'per'    => $per,
        'page'   => max(1, (int) ($query['page'] ?? 1)),
    ];
}

/**
 * WHERE clause shared by the list, the count and the export.
 *
 * @param array{q: string, status: string} $state
 * @return array{0: string, 1: array<string, string>} [sql, named params]
 */
function bdst_reg_where(array $state): array
{
    $where  = '1';
    $params = [];

    if ($state['q'] !== '') {
        // Identity, place and organisation are all searched, so an administrator
        // can look someone up by name, by where they are ("Arusha", "Kinondoni")
        // or by the firm they run ("Zanzibar Trade Forum").
        $where .= ' AND (full_name LIKE :q1 OR email LIKE :q2 OR reference_no LIKE :q3'
            . ' OR phone LIKE :q4 OR region LIKE :q5 OR district LIKE :q6'
            . ' OR firm_name LIKE :q7)';
        $like = '%' . $state['q'] . '%';
        $params += [
            ':q1' => $like,
            ':q2' => $like,
            ':q3' => $like,
            ':q4' => $like,
            ':q5' => $like,
            ':q6' => $like,
            ':q7' => $like,
        ];
    }
    if ($state['status'] !== '') {
        $where .= ' AND status = :status';
        $params[':status'] = $state['status'];
    }

    return [$where, $params];
}

/**
 * ORDER BY fragment for an already-validated sort key.
 */
function bdst_reg_order_by(string $sort, string $dir): string
{
    $column = bdst_reg_sort_columns()[$sort] ?? 'submitted_at';
    $sqlDir = $dir === 'asc' ? 'ASC' : 'DESC';

    return $column . ' ' . $sqlDir . ', id ' . $sqlDir;
}

/**
 * Query string for the current list state, omitting defaults so URLs stay
 * short and readable.
 *
 * @param array<string, mixed> $state
 * @param array<string, mixed> $overrides
 * @param list<string>         $drop
 */
function bdst_reg_query_params(array $state, array $overrides = [], array $drop = []): string
{
    $defaults = ['q' => '', 'status' => '', 'sort' => 'date', 'dir' => 'desc', 'per' => 15, 'page' => 1];
    $merged   = array_merge($state, $overrides);

    foreach ($drop as $key) {
        unset($merged[$key]);
    }

    $out = [];
    foreach ($defaults as $key => $default) {
        if (!array_key_exists($key, $merged)) {
            continue;
        }
        if ((string) $merged[$key] !== (string) $default) {
            $out[$key] = $merged[$key];
        }
    }

    return $out === [] ? '' : http_build_query($out);
}

/**
 * Link back to the list, optionally overriding part of the state.
 *
 * @param array<string, mixed> $state
 * @param array<string, mixed> $overrides
 */
function bdst_reg_list_url(array $state, array $overrides = []): string
{
    $qs = bdst_reg_query_params($state, $overrides);

    return 'registrations.php' . ($qs === '' ? '' : '?' . $qs);
}

/**
 * Delete registrations by id, removing their uploaded CVs as well.
 *
 * @param list<int> $ids
 * @return int rows deleted
 */
function bdst_reg_delete_ids(PDO $pdo, array $ids): int
{
    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("SELECT cv_path FROM bds_registrations WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    $uploads = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'register' . DIRECTORY_SEPARATOR . 'uploads';
    foreach ($stmt->fetchAll() as $row) {
        $cv = (string) ($row['cv_path'] ?? '');
        if ($cv === '') {
            continue;
        }
        $file = $uploads . DIRECTORY_SEPARATOR . basename($cv);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM bds_registrations WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    return $stmt->rowCount();
}
