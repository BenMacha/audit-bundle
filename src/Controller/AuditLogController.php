<?php

namespace BenMacha\AuditBundle\Controller;

use BenMacha\AuditBundle\Entity\AuditLog;
use BenMacha\AuditBundle\Repository\AuditChangeRepository;
use BenMacha\AuditBundle\Repository\AuditLogRepository;
use BenMacha\AuditBundle\Repository\EntityConfigRepository;
use BenMacha\AuditBundle\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/audit/logs', name: 'audit_logs_')]
class AuditLogController extends AbstractController
{
    private AuditLogRepository $auditLogRepository;
    private AuditChangeRepository $auditChangeRepository;
    private EntityConfigRepository $entityConfigRepository;
    private ConfigurationService $configurationService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        AuditLogRepository $auditLogRepository,
        AuditChangeRepository $auditChangeRepository,
        EntityConfigRepository $entityConfigRepository,
        ConfigurationService $configurationService,
        EntityManagerInterface $entityManager
    ) {
        $this->auditLogRepository = $auditLogRepository;
        $this->auditChangeRepository = $auditChangeRepository;
        $this->entityConfigRepository = $entityConfigRepository;
        $this->configurationService = $configurationService;
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'index')]
    #[IsGranted('ROLE_AUDIT_AUDITOR')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->configurationService->getItemsPerPage();
        $offset = ($page - 1) * $limit;

        // Build filters from request
        $filters = $this->buildFiltersFromRequest($request);

        // Get audit logs with pagination
        $auditLogs = $this->auditLogRepository->findWithFilters($filters, $limit, $offset);
        $totalCount = $this->auditLogRepository->countByFilters($filters);
        $totalPages = ceil($totalCount / $limit);

        // Get filter options for dropdowns
        $filterOptions = $this->getFilterOptions();

        return $this->render('@Audit/logs/index.html.twig', [
            'audit_logs' => $auditLogs,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'limit' => $limit,
            'filters' => $filters,
            'filter_options' => $filterOptions,
            'show_ip_addresses' => $this->configurationService->shouldShowIpAddresses(),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_AUDIT_AUDITOR')]
    public function show(AuditLog $auditLog): Response
    {
        // Get related changes
        $changes = $this->auditChangeRepository->findByAuditLog($auditLog);

        // Get entity history (previous and next logs for same entity)
        $entityHistory = $this->getEntityHistory($auditLog);

        // Parse old and new values
        $oldValues = $auditLog->getOldValues() ? json_decode($auditLog->getOldValues(), true) : [];
        $newValues = $auditLog->getNewValues() ? json_decode($auditLog->getNewValues(), true) : [];
        $metadata = $auditLog->getMetadata() ? json_decode($auditLog->getMetadata(), true) : [];

        // Get entity configuration
        $entityConfig = $this->entityConfigRepository->findByEntityClass($auditLog->getEntityClass());

        return $this->render('@Audit/logs/show.html.twig', [
            'audit_log' => $auditLog,
            'changes' => $changes,
            'entity_history' => $entityHistory,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'entity_config' => $entityConfig,
            'show_ip_addresses' => $this->configurationService->shouldShowIpAddresses(),
        ]);
    }

    #[Route('/entity/{entityClass}/{entityId}', name: 'entity_history')]
    #[IsGranted('ROLE_AUDIT_AUDITOR')]
    public function entityHistory(Request $request, string $entityClass, string $entityId): Response
    {
        // Decode entity class from URL
        $entityClass = urldecode($entityClass);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->configurationService->getItemsPerPage();
        $offset = ($page - 1) * $limit;

        // Get audit logs for this specific entity
        $auditLogs = $this->auditLogRepository->findByEntity($entityClass, $entityId, $limit, $offset);
        $totalCount = $this->auditLogRepository->countByFilters([
            'entity_class' => $entityClass,
            'entity_id' => $entityId,
        ]);
        $totalPages = ceil($totalCount / $limit);

        // Get entity configuration
        $entityConfig = $this->entityConfigRepository->findByEntityClass($entityClass);

        // Get entity name for display
        $entityName = $this->getEntityDisplayName($entityClass, $entityId);

        return $this->render('@Audit/logs/entity_history.html.twig', [
            'audit_logs' => $auditLogs,
            'entity_class' => $entityClass,
            'entity_id' => $entityId,
            'entity_name' => $entityName,
            'entity_config' => $entityConfig,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'limit' => $limit,
            'show_ip_addresses' => $this->configurationService->shouldShowIpAddresses(),
        ]);
    }

    #[Route('/user/{userId}', name: 'user_activity')]
    #[IsGranted('ROLE_AUDIT_AUDITOR')]
    public function userActivity(Request $request, string $userId): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->configurationService->getItemsPerPage();
        $offset = ($page - 1) * $limit;

        // Get audit logs for this user
        $auditLogs = $this->auditLogRepository->findByUser($userId, $limit, $offset);
        $totalCount = $this->auditLogRepository->countByFilters(['user_id' => $userId]);
        $totalPages = ceil($totalCount / $limit);

        // Get user statistics
        $userStats = $this->auditLogRepository->getStatisticsByUser(1, $userId);

        return $this->render('@Audit/logs/user_activity.html.twig', [
            'audit_logs' => $auditLogs,
            'user_id' => $userId,
            'user_stats' => $userStats[0] ?? null,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'limit' => $limit,
            'show_ip_addresses' => $this->configurationService->shouldShowIpAddresses(),
        ]);
    }

    #[Route('/search', name: 'search')]
    #[IsGranted('ROLE_AUDIT_AUDITOR')]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->configurationService->getItemsPerPage();
        $offset = ($page - 1) * $limit;

        $auditLogs = [];
        $totalCount = 0;
        $totalPages = 0;

        if (!empty($query)) {
            // Search in multiple fields
            $searchFilters = [
                'search' => $query,
            ];

            $auditLogs = $this->auditLogRepository->findWithFilters($searchFilters, $limit, $offset);
            $totalCount = $this->auditLogRepository->countByFilters($searchFilters);
            $totalPages = ceil($totalCount / $limit);
        }

        return $this->render('@Audit/logs/search.html.twig', [
            'audit_logs' => $auditLogs,
            'query' => $query,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'limit' => $limit,
            'show_ip_addresses' => $this->configurationService->shouldShowIpAddresses(),
        ]);
    }

    #[Route('/compare/{id1}/{id2}', name: 'compare', requirements: ['id1' => '\d+', 'id2' => '\d+'])]
    #[IsGranted('ROLE_AUDIT_AUDITOR')]
    public function compare(AuditLog $auditLog1, AuditLog $auditLog2): Response
    {
        // Ensure both logs are for the same entity
        if ($auditLog1->getEntityClass() !== $auditLog2->getEntityClass()
            || $auditLog1->getEntityId() !== $auditLog2->getEntityId()) {
            throw $this->createNotFoundException('Cannot compare logs for different entities');
        }

        // Get changes for both logs
        $changes1 = $this->auditChangeRepository->findByAuditLog($auditLog1);
        $changes2 = $this->auditChangeRepository->findByAuditLog($auditLog2);

        // Parse values
        $values1 = $this->parseAuditLogValues($auditLog1);
        $values2 = $this->parseAuditLogValues($auditLog2);

        // Compare changes
        $comparison = $this->compareAuditLogs($auditLog1, $auditLog2, $changes1, $changes2);

        return $this->render('@Audit/logs/compare.html.twig', [
            'audit_log_1' => $auditLog1,
            'audit_log_2' => $auditLog2,
            'changes_1' => $changes1,
            'changes_2' => $changes2,
            'values_1' => $values1,
            'values_2' => $values2,
            'comparison' => $comparison,
            'show_ip_addresses' => $this->configurationService->shouldShowIpAddresses(),
        ]);
    }

    #[Route('/ajax/filter-options', name: 'ajax_filter_options', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_AUDITOR')]
    public function ajaxFilterOptions(Request $request): JsonResponse
    {
        $type = $request->query->get('type');
        $search = $request->query->get('search', '');

        $options = [];

        switch ($type) {
            case 'entity_classes':
                $options = $this->auditLogRepository->getUniqueEntityClasses();
                break;
            case 'users':
                $options = $this->auditLogRepository->getUniqueUsers();
                break;
            case 'operations':
                $options = ['INSERT', 'UPDATE', 'DELETE', 'ROLLBACK', 'BULK_ROLLBACK'];
                break;
        }

        // Filter options based on search term
        if (!empty($search)) {
            $options = array_filter($options, function ($option) use ($search) {
                return false !== stripos($option, $search);
            });
        }

        return $this->json(array_values($options));
    }

    #[Route('/ajax/entity-info', name: 'ajax_entity_info', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_AUDITOR')]
    public function ajaxEntityInfo(Request $request): JsonResponse
    {
        $entityClass = $request->query->get('entity_class');
        $entityId = $request->query->get('entity_id');

        if (!$entityClass || !$entityId) {
            return $this->json(['error' => 'Missing parameters'], 400);
        }

        // Get entity information
        $entityName = $this->getEntityDisplayName($entityClass, $entityId);
        $auditCount = $this->auditLogRepository->countByFilters([
            'entity_class' => $entityClass,
            'entity_id' => $entityId,
        ]);

        // Get latest audit log
        $latestLog = $this->auditLogRepository->findByEntity($entityClass, $entityId, 1, 0);
        $latestActivity = !empty($latestLog) ? $latestLog[0]->getCreatedAt()->format('Y-m-d H:i:s') : null;

        return $this->json([
            'entity_name' => $entityName,
            'audit_count' => $auditCount,
            'latest_activity' => $latestActivity,
        ]);
    }

    /**
     * Build filters array from request parameters.
     */
    private function buildFiltersFromRequest(Request $request): array
    {
        $filters = [];

        // Date range filters
        if ($request->query->get('start_date')) {
            $filters['start_date'] = new \DateTime($request->query->get('start_date'));
        }
        if ($request->query->get('end_date')) {
            $filters['end_date'] = new \DateTime($request->query->get('end_date'));
        }

        // Entity filters
        if ($request->query->get('entity_class')) {
            $filters['entity_class'] = $request->query->get('entity_class');
        }
        if ($request->query->get('entity_id')) {
            $filters['entity_id'] = $request->query->get('entity_id');
        }

        // Operation filter
        if ($request->query->get('operation')) {
            $filters['operation'] = $request->query->get('operation');
        }

        // User filters
        if ($request->query->get('user_id')) {
            $filters['user_id'] = $request->query->get('user_id');
        }
        if ($request->query->get('username')) {
            $filters['username'] = $request->query->get('username');
        }

        // IP address filter
        if ($request->query->get('ip_address')) {
            $filters['ip_address'] = $request->query->get('ip_address');
        }

        return $filters;
    }

    /**
     * Get filter options for dropdowns.
     */
    private function getFilterOptions(): array
    {
        return [
            'entity_classes' => $this->auditLogRepository->getUniqueEntityClasses(),
            'operations' => ['INSERT', 'UPDATE', 'DELETE', 'ROLLBACK', 'BULK_ROLLBACK'],
            'users' => $this->auditLogRepository->getUniqueUsers(),
        ];
    }

    /**
     * Get entity history (previous and next logs).
     */
    private function getEntityHistory(AuditLog $auditLog): array
    {
        // Get previous log
        $previousLog = $this->auditLogRepository->createQueryBuilder('al')
            ->where('al.entityClass = :entityClass')
            ->andWhere('al.entityId = :entityId')
            ->andWhere('al.createdAt < :createdAt')
            ->setParameter('entityClass', $auditLog->getEntityClass())
            ->setParameter('entityId', $auditLog->getEntityId())
            ->setParameter('createdAt', $auditLog->getCreatedAt())
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Get next log
        $nextLog = $this->auditLogRepository->createQueryBuilder('al')
            ->where('al.entityClass = :entityClass')
            ->andWhere('al.entityId = :entityId')
            ->andWhere('al.createdAt > :createdAt')
            ->setParameter('entityClass', $auditLog->getEntityClass())
            ->setParameter('entityId', $auditLog->getEntityId())
            ->setParameter('createdAt', $auditLog->getCreatedAt())
            ->orderBy('al.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'previous' => $previousLog,
            'next' => $nextLog,
        ];
    }

    /**
     * Get entity display name.
     */
    private function getEntityDisplayName(string $entityClass, $entityId): string
    {
        // Try to get a meaningful name from the entity
        try {
            $repository = $this->entityManager->getRepository($entityClass);
            $entity = $repository->find($entityId);

            if ($entity) {
                // Try common name methods
                $nameMethods = ['getName', 'getTitle', 'getLabel', '__toString'];
                foreach ($nameMethods as $method) {
                    if (method_exists($entity, $method)) {
                        $name = $entity->$method();
                        if (!empty($name)) {
                            return $name;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Entity might not exist anymore
        }

        // Fallback to class name and ID
        $shortClassName = substr(strrchr($entityClass, '\\'), 1);

        return "{$shortClassName} #{$entityId}";
    }

    /**
     * Parse audit log values.
     */
    private function parseAuditLogValues(AuditLog $auditLog): array
    {
        return [
            'old' => $auditLog->getOldValues() ? json_decode($auditLog->getOldValues(), true) : [],
            'new' => $auditLog->getNewValues() ? json_decode($auditLog->getNewValues(), true) : [],
            'metadata' => $auditLog->getMetadata() ? json_decode($auditLog->getMetadata(), true) : [],
        ];
    }

    /**
     * Compare two audit logs.
     */
    private function compareAuditLogs(AuditLog $log1, AuditLog $log2, array $changes1, array $changes2): array
    {
        $comparison = [
            'time_diff' => $log2->getCreatedAt()->getTimestamp() - $log1->getCreatedAt()->getTimestamp(),
            'same_user' => $log1->getUserId() === $log2->getUserId(),
            'same_ip' => $log1->getIpAddress() === $log2->getIpAddress(),
            'field_changes' => [],
        ];

        // Compare field changes
        $allFields = [];
        foreach ($changes1 as $change) {
            $allFields[$change->getFieldName()] = ['log1' => $change, 'log2' => null];
        }
        foreach ($changes2 as $change) {
            if (isset($allFields[$change->getFieldName()])) {
                $allFields[$change->getFieldName()]['log2'] = $change;
            } else {
                $allFields[$change->getFieldName()] = ['log1' => null, 'log2' => $change];
            }
        }

        foreach ($allFields as $fieldName => $fieldChanges) {
            $comparison['field_changes'][$fieldName] = [
                'in_both' => null !== $fieldChanges['log1'] && null !== $fieldChanges['log2'],
                'only_in_log1' => null !== $fieldChanges['log1'] && null === $fieldChanges['log2'],
                'only_in_log2' => null === $fieldChanges['log1'] && null !== $fieldChanges['log2'],
                'same_old_value' => $fieldChanges['log1'] && $fieldChanges['log2'] ?
                    $fieldChanges['log1']->getOldValue() === $fieldChanges['log2']->getOldValue() : false,
                'same_new_value' => $fieldChanges['log1'] && $fieldChanges['log2'] ?
                    $fieldChanges['log1']->getNewValue() === $fieldChanges['log2']->getNewValue() : false,
            ];
        }

        return $comparison;
    }
}
