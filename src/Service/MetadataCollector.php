<?php

namespace BenMacha\AuditBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class MetadataCollector
{
    private RequestStack $requestStack;
    private Security $security;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private array $customCollectors = [];

    public function __construct(
        RequestStack $requestStack,
        Security $security,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Collect metadata for an audit operation.
     */
    public function collectMetadata(object $entity, string $operation): array
    {
        $metadata = [];

        try {
            // Basic metadata
            $metadata['timestamp'] = (new \DateTime())->format('Y-m-d H:i:s');
            $metadata['operation'] = $operation;
            $metadata['entity_class'] = get_class($entity);

            // Request metadata
            $metadata = array_merge($metadata, $this->collectRequestMetadata());

            // User metadata
            $metadata = array_merge($metadata, $this->collectUserMetadata());

            // Entity metadata
            $metadata = array_merge($metadata, $this->collectEntityMetadata($entity));

            // System metadata
            $metadata = array_merge($metadata, $this->collectSystemMetadata());

            // Custom metadata from registered collectors
            foreach ($this->customCollectors as $collector) {
                if (is_callable($collector)) {
                    try {
                        $customData = $collector($entity, $operation);
                        if (is_array($customData)) {
                            $metadata = array_merge($metadata, $customData);
                        }
                    } catch (\Exception $e) {
                        $this->logger->warning('Custom metadata collector failed', [
                            'collector' => get_class($collector),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Metadata collection failed', [
                'entity_class' => get_class($entity),
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
        }

        return $metadata;
    }

    /**
     * Collect request-related metadata.
     */
    private function collectRequestMetadata(): array
    {
        $metadata = [];
        $request = $this->requestStack->getCurrentRequest();

        if ($request) {
            $metadata['request'] = [
                'method' => $request->getMethod(),
                'uri' => $request->getRequestUri(),
                'route' => $request->attributes->get('_route'),
                'controller' => $request->attributes->get('_controller'),
                'referer' => $request->headers->get('referer'),
                'content_type' => $request->headers->get('content-type'),
                'accept' => $request->headers->get('accept'),
                'accept_language' => $request->headers->get('accept-language'),
                'session_id' => $request->hasSession() ? $request->getSession()->getId() : null,
            ];

            // Add query parameters (excluding sensitive data)
            $queryParams = $request->query->all();
            $metadata['request']['query_params'] = $this->filterSensitiveData($queryParams);

            // Add request body for certain operations (excluding sensitive data)
            if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
                $requestData = $request->request->all();
                $metadata['request']['body_params'] = $this->filterSensitiveData($requestData);
            }
        } else {
            $metadata['request'] = [
                'source' => 'cli_or_background',
            ];
        }

        return $metadata;
    }

    /**
     * Collect user-related metadata.
     */
    private function collectUserMetadata(): array
    {
        $metadata = [];
        $user = $this->security->getUser();

        if ($user) {
            $metadata['user'] = [
                'class' => get_class($user),
                'roles' => $user->getRoles(),
            ];

            // Add additional user information if available
            if (method_exists($user, 'getEmail')) {
                $metadata['user']['email'] = $user->getEmail();
            }

            if (method_exists($user, 'getFullName')) {
                $metadata['user']['full_name'] = $user->getFullName();
            }

            if (method_exists($user, 'getLastLoginAt')) {
                $lastLogin = $user->getLastLoginAt();
                $metadata['user']['last_login'] = $lastLogin ? $lastLogin->format('Y-m-d H:i:s') : null;
            }
        } else {
            $metadata['user'] = [
                'authenticated' => false,
                'type' => 'anonymous',
            ];
        }

        return $metadata;
    }

    /**
     * Collect entity-related metadata.
     */
    private function collectEntityMetadata(object $entity): array
    {
        $metadata = [];

        try {
            $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));

            $metadata['entity'] = [
                'table_name' => $classMetadata->getTableName(),
                'identifier_fields' => $classMetadata->getIdentifierFieldNames(),
                'field_count' => count($classMetadata->getFieldNames()),
                'association_count' => count($classMetadata->getAssociationNames()),
            ];

            // Add entity state information
            $unitOfWork = $this->entityManager->getUnitOfWork();
            $entityState = $unitOfWork->getEntityState($entity);
            $metadata['entity']['state'] = $this->getEntityStateName($entityState);

            // Add creation/modification timestamps if available
            if (method_exists($entity, 'getCreatedAt')) {
                $createdAt = $entity->getCreatedAt();
                $metadata['entity']['created_at'] = $createdAt ? $createdAt->format('Y-m-d H:i:s') : null;
            }

            if (method_exists($entity, 'getUpdatedAt')) {
                $updatedAt = $entity->getUpdatedAt();
                $metadata['entity']['updated_at'] = $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : null;
            }

            // Add version information if entity supports optimistic locking
            if (method_exists($entity, 'getVersion')) {
                $metadata['entity']['version'] = $entity->getVersion();
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to collect entity metadata', [
                'entity_class' => get_class($entity),
                'error' => $e->getMessage(),
            ]);
        }

        return $metadata;
    }

    /**
     * Collect system-related metadata.
     */
    private function collectSystemMetadata(): array
    {
        return [
            'system' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'] ?? 0,
                'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
                'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
                'process_id' => getmypid(),
            ],
        ];
    }

    /**
     * Filter sensitive data from arrays.
     */
    private function filterSensitiveData(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'passwd',
            'secret',
            'token',
            'api_key',
            'apikey',
            'auth',
            'authorization',
            'credit_card',
            'creditcard',
            'ssn',
            'social_security',
            'pin',
            'cvv',
            'cvc',
        ];

        $filtered = [];
        foreach ($data as $key => $value) {
            $keyLower = strtolower($key);
            $isSensitive = false;

            foreach ($sensitiveKeys as $sensitiveKey) {
                if (false !== strpos($keyLower, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $filtered[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $filtered[$key] = $this->filterSensitiveData($value);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Get entity state name.
     */
    private function getEntityStateName(int $state): string
    {
        switch ($state) {
            case 1: // UnitOfWork::STATE_MANAGED
                return 'managed';
            case 2: // UnitOfWork::STATE_NEW
                return 'new';
            case 3: // UnitOfWork::STATE_DETACHED
                return 'detached';
            case 4: // UnitOfWork::STATE_REMOVED
                return 'removed';
            default:
                return 'unknown';
        }
    }

    /**
     * Register a custom metadata collector.
     */
    public function addCustomCollector(callable $collector): void
    {
        $this->customCollectors[] = $collector;
    }

    /**
     * Remove all custom collectors.
     */
    public function clearCustomCollectors(): void
    {
        $this->customCollectors = [];
    }

    /**
     * Get registered custom collectors count.
     */
    public function getCustomCollectorsCount(): int
    {
        return count($this->customCollectors);
    }

    /**
     * Collect metadata for batch operations.
     */
    public function collectBatchMetadata(array $entities, string $operation): array
    {
        $metadata = [
            'batch' => [
                'operation' => $operation,
                'entity_count' => count($entities),
                'entity_classes' => array_unique(array_map('get_class', $entities)),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            ],
        ];

        // Add request and user metadata (same for all entities in batch)
        $metadata = array_merge($metadata, $this->collectRequestMetadata());
        $metadata = array_merge($metadata, $this->collectUserMetadata());
        $metadata = array_merge($metadata, $this->collectSystemMetadata());

        return $metadata;
    }
}
