<?php

/**
 * app/models/BaseRepository.php
 *
 * Module file for the DashMed-SAE project.
 *
 * Notes:
 * - This docblock is intentionally file-scoped.
 * - Detailed PHPDoc for classes/methods is maintained near declarations.
 *
 * @package DashMed\SAE
 */

declare(strict_types=1);

namespace modules\models;

use assets\includes\Database;
use PDO;

/**
 * Class BaseRepository
 *
 * Parent class for all repositories. Centralizes PDO connection management.
 *
 * @package DashMed\Modules\Models\Base
 * @author  DashMed Team
 * @license Proprietary
 */
abstract class BaseRepository
{
    /**
     * @var PDO Database connection
     */
    protected PDO $pdo;

    /**
     * Constructor
     *
     * @param PDO|null $pdo Database connection (optional, defaults to singleton)
     */
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }
}
