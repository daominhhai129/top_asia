<?php
declare(strict_types=1);

namespace DBHDL\Sync\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Unified contract implemented by sync transport engines (GitHub, Same-Server, Remote REST, ZIP Upload).
 */
interface TransportInterface {

    /**
     * Machine identifier for this transport.
     */
    public function getId(): string;

    /**
     * Verify connectivity and permissions without mutating target data.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function testConnection( array $config = [] ): array;

    /**
     * Compute a non-destructive dry-run diff preview.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function previewDiff( array $request ): array;

    /**
     * Execute the sync operation safely with snapshot protection.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function execute( array $request ): array;
}
