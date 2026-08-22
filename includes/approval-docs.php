<?php
declare(strict_types=1);

/**
 * BDS Tanzania — membership approval documents.
 *
 * Every approved member receives these files as email attachments and can
 * download them from the Check Status page. One registry drives both, so the
 * two never drift apart.
 *
 * Production note: keep the folder OUTSIDE the web root on cPanel (e.g.
 * /home/USER/approval-docs) and point 'approvalDocsPath' in mail-config.php
 * at it. The default below works for local development only.
 */

function bdst_approval_docs_path(): string
{
    $config = bdst_config();
    $configured = (string) ($config['approvalDocsPath'] ?? '');
    if ($configured !== '') {
        return $configured;
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'approval-docs';
}

/**
 * @return array<string, array{file: string, title: string, description: string}>
 */
function bdst_approval_docs(): array
{
    return [
        'system-architecture' => [
            'file'        => 'SYSTEM_ARCHITECTURE.pdf',
            'title'       => 'System Architecture Guide',
            'description' => 'Overview of the BDS Tanzania member framework.',
        ],
    ];
}

/** Resolve a slug to an existing file path, or null when unknown/missing. */
function bdst_approval_doc_file(string $slug): ?string
{
    $docs = bdst_approval_docs();
    if (!isset($docs[$slug])) {
        return null;
    }

    $path = bdst_approval_docs_path() . DIRECTORY_SEPARATOR . $docs[$slug]['file'];

    return is_file($path) ? $path : null;
}
