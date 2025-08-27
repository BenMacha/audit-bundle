<?php

namespace BenMacha\AuditBundle\Controller;

use BenMacha\AuditBundle\Entity\AuditLog;
use BenMacha\AuditBundle\Repository\AuditLogRepository;
use BenMacha\AuditBundle\Service\ConfigurationService;
use BenMacha\AuditBundle\Service\RollbackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/audit/rollback', name: 'audit_rollback_')]
class RollbackController extends AbstractController
{
    private RollbackService $rollbackService;
    private AuditLogRepository $auditLogRepository;
    private ConfigurationService $configurationService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        RollbackService $rollbackService,
        AuditLogRepository $auditLogRepository,
        ConfigurationService $configurationService,
        EntityManagerInterface $entityManager
    ) {
        $this->rollbackService = $rollbackService;
        $this->auditLogRepository = $auditLogRepository;
        $this->configurationService = $configurationService;
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'index')]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->configurationService->getItemsPerPage();
        $offset = ($page - 1) * $limit;

        // Get recent rollback history
        $rollbackHistory = $this->rollbackService->getRollbackHistory($limit, $offset);
        $totalCount = $this->auditLogRepository->countByFilters(['operation' => ['ROLLBACK', 'BULK_ROLLBACK']]);
        $totalPages = ceil($totalCount / $limit);

        // Get rollback statistics
        $rollbackStats = $this->getRollbackStatistics();

        return $this->render('@Audit/rollback/index.html.twig', [
            'rollback_history' => $rollbackHistory,
            'rollback_stats' => $rollbackStats,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'limit' => $limit,
        ]);
    }

    #[Route('/preview/{id}', name: 'preview', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function preview(AuditLog $auditLog): Response
    {
        // Check if rollback is possible
        if (!$this->rollbackService->canRollback($auditLog)) {
            $this->addFlash('error', 'This audit log cannot be rolled back.');

            return $this->redirectToRoute('audit_logs_show', ['id' => $auditLog->getId()]);
        }

        // Get rollback preview
        $preview = $this->rollbackService->getRollbackPreview($auditLog);

        // Check for conflicts
        $conflicts = $this->rollbackService->hasConflicts($auditLog);

        return $this->render('@Audit/rollback/preview.html.twig', [
            'audit_log' => $auditLog,
            'preview' => $preview,
            'conflicts' => $conflicts,
            'can_rollback' => !$conflicts,
        ]);
    }

    #[Route('/execute/{id}', name: 'execute', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function execute(Request $request, AuditLog $auditLog): Response
    {
        // Verify CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('rollback_' . $auditLog->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token.');

            return $this->redirectToRoute('audit_rollback_preview', ['id' => $auditLog->getId()]);
        }

        // Check if rollback is possible
        if (!$this->rollbackService->canRollback($auditLog)) {
            $this->addFlash('error', 'This audit log cannot be rolled back.');

            return $this->redirectToRoute('audit_rollback_preview', ['id' => $auditLog->getId()]);
        }

        // Check for conflicts
        if ($this->rollbackService->hasConflicts($auditLog)) {
            $force = $request->request->getBoolean('force', false);
            if (!$force) {
                $this->addFlash('error', 'Rollback conflicts detected. Use force option to proceed anyway.');

                return $this->redirectToRoute('audit_rollback_preview', ['id' => $auditLog->getId()]);
            }
        }

        try {
            // Execute rollback
            $result = $this->rollbackService->rollbackAuditLog($auditLog);

            if ($result['success']) {
                $this->addFlash('success', 'Rollback executed successfully.');

                return $this->redirectToRoute('audit_logs_show', ['id' => $result['rollback_log']->getId()]);
            } else {
                $this->addFlash('error', 'Rollback failed: ' . $result['error']);
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Rollback failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('audit_rollback_preview', ['id' => $auditLog->getId()]);
    }

    #[Route('/bulk', name: 'bulk')]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function bulk(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $auditLogIds = $request->request->get('audit_log_ids', []);

            if (empty($auditLogIds)) {
                $this->addFlash('error', 'Please select audit logs to rollback.');

                return $this->redirectToRoute('audit_rollback_bulk');
            }

            // Verify CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('bulk_rollback', $token)) {
                $this->addFlash('error', 'Invalid security token.');

                return $this->redirectToRoute('audit_rollback_bulk');
            }

            // Get audit logs
            $auditLogs = $this->auditLogRepository->findBy(['id' => $auditLogIds]);

            if (empty($auditLogs)) {
                $this->addFlash('error', 'No valid audit logs found.');

                return $this->redirectToRoute('audit_rollback_bulk');
            }

            try {
                // Execute bulk rollback
                $result = $this->rollbackService->rollbackMultiple($auditLogs);

                $successCount = count($result['successful']);
                $failedCount = count($result['failed']);

                if ($successCount > 0) {
                    $this->addFlash('success', "Successfully rolled back {$successCount} audit log(s).");
                }

                if ($failedCount > 0) {
                    $this->addFlash('warning', "Failed to rollback {$failedCount} audit log(s).");
                }

                return $this->redirectToRoute('audit_rollback_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Bulk rollback failed: ' . $e->getMessage());
            }
        }

        // Get recent audit logs for selection
        $recentLogs = $this->auditLogRepository->findBy(
            ['operation' => ['INSERT', 'UPDATE', 'DELETE']],
            ['createdAt' => 'DESC'],
            50
        );

        return $this->render('@Audit/rollback/bulk.html.twig', [
            'recent_logs' => $recentLogs,
        ]);
    }

    #[Route('/entity/{entityClass}/{entityId}/to-date', name: 'entity_to_date')]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function entityToDate(Request $request, string $entityClass, string $entityId): Response
    {
        // Decode entity class from URL
        $entityClass = urldecode($entityClass);

        if ($request->isMethod('POST')) {
            $targetDate = $request->request->get('target_date');

            if (empty($targetDate)) {
                $this->addFlash('error', 'Please specify a target date.');

                return $this->redirectToRoute('audit_rollback_entity_to_date', [
                    'entityClass' => urlencode($entityClass),
                    'entityId' => $entityId,
                ]);
            }

            // Verify CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('entity_rollback', $token)) {
                $this->addFlash('error', 'Invalid security token.');

                return $this->redirectToRoute('audit_rollback_entity_to_date', [
                    'entityClass' => urlencode($entityClass),
                    'entityId' => $entityId,
                ]);
            }

            try {
                $targetDateTime = new \DateTime($targetDate);

                // Execute entity rollback to date
                $result = $this->rollbackService->rollbackEntityToDate($entityClass, $entityId, $targetDateTime);

                if ($result['success']) {
                    $this->addFlash('success', 'Entity successfully rolled back to ' . $targetDateTime->format('Y-m-d H:i:s'));

                    return $this->redirectToRoute('audit_logs_entity_history', [
                        'entityClass' => urlencode($entityClass),
                        'entityId' => $entityId,
                    ]);
                } else {
                    $this->addFlash('error', 'Rollback failed: ' . $result['error']);
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Rollback failed: ' . $e->getMessage());
            }
        }

        // Get entity audit history
        $auditHistory = $this->auditLogRepository->findByEntity($entityClass, $entityId, 20, 0);

        // Get entity name for display
        $entityName = $this->getEntityDisplayName($entityClass, $entityId);

        return $this->render('@Audit/rollback/entity_to_date.html.twig', [
            'entity_class' => $entityClass,
            'entity_id' => $entityId,
            'entity_name' => $entityName,
            'audit_history' => $auditHistory,
        ]);
    }

    #[Route('/history/{id}', name: 'history', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function history(AuditLog $rollbackLog): Response
    {
        // Ensure this is a rollback log
        if (!in_array($rollbackLog->getOperation(), ['ROLLBACK', 'BULK_ROLLBACK'])) {
            throw $this->createNotFoundException('This is not a rollback log.');
        }

        // Get rollback details from metadata
        $metadata = $rollbackLog->getMetadata() ? json_decode($rollbackLog->getMetadata(), true) : [];
        $rollbackDetails = $metadata['rollback_details'] ?? [];

        // Get original audit logs if available
        $originalLogs = [];
        if (isset($rollbackDetails['original_log_ids'])) {
            $originalLogs = $this->auditLogRepository->findBy([
                'id' => $rollbackDetails['original_log_ids'],
            ]);
        }

        return $this->render('@Audit/rollback/history.html.twig', [
            'rollback_log' => $rollbackLog,
            'rollback_details' => $rollbackDetails,
            'original_logs' => $originalLogs,
        ]);
    }

    #[Route('/ajax/check-conflicts/{id}', name: 'ajax_check_conflicts', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function ajaxCheckConflicts(AuditLog $auditLog): JsonResponse
    {
        try {
            $conflicts = $this->rollbackService->hasConflicts($auditLog);
            $canRollback = $this->rollbackService->canRollback($auditLog);
            $isAlreadyRolledBack = $this->rollbackService->isAlreadyRolledBack($auditLog);

            return $this->json([
                'has_conflicts' => $conflicts,
                'can_rollback' => $canRollback,
                'already_rolled_back' => $isAlreadyRolledBack,
                'conflicts_details' => $conflicts ? $this->getConflictDetails($auditLog) : null,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/ajax/preview/{id}', name: 'ajax_preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function ajaxPreview(AuditLog $auditLog): JsonResponse
    {
        try {
            if (!$this->rollbackService->canRollback($auditLog)) {
                return $this->json(['error' => 'Cannot rollback this audit log'], 400);
            }

            $preview = $this->rollbackService->getRollbackPreview($auditLog);

            return $this->json($preview);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/ajax/bulk-preview', name: 'ajax_bulk_preview', methods: ['POST'])]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function ajaxBulkPreview(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $auditLogIds = $data['audit_log_ids'] ?? [];

        if (empty($auditLogIds)) {
            return $this->json(['error' => 'No audit log IDs provided'], 400);
        }

        try {
            $auditLogs = $this->auditLogRepository->findBy(['id' => $auditLogIds]);

            $previews = [];
            $conflicts = [];

            foreach ($auditLogs as $auditLog) {
                if ($this->rollbackService->canRollback($auditLog)) {
                    $previews[] = [
                        'id' => $auditLog->getId(),
                        'preview' => $this->rollbackService->getRollbackPreview($auditLog),
                        'has_conflicts' => $this->rollbackService->hasConflicts($auditLog),
                    ];
                } else {
                    $conflicts[] = [
                        'id' => $auditLog->getId(),
                        'reason' => 'Cannot rollback this audit log',
                    ];
                }
            }

            return $this->json([
                'previews' => $previews,
                'conflicts' => $conflicts,
                'total_selected' => count($auditLogIds),
                'rollbackable' => count($previews),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get rollback statistics.
     */
    private function getRollbackStatistics(): array
    {
        $stats = $this->auditLogRepository->getStatisticsByOperation();

        $rollbackStats = [
            'total_rollbacks' => 0,
            'successful_rollbacks' => 0,
            'failed_rollbacks' => 0,
            'bulk_rollbacks' => 0,
        ];

        foreach ($stats as $stat) {
            switch ($stat['operation']) {
                case 'ROLLBACK':
                    $rollbackStats['total_rollbacks'] += $stat['count'];
                    $rollbackStats['successful_rollbacks'] += $stat['count'];
                    break;
                case 'BULK_ROLLBACK':
                    $rollbackStats['bulk_rollbacks'] += $stat['count'];
                    break;
            }
        }

        // Get recent rollback activity
        $recentActivity = $this->auditLogRepository->findBy(
            ['operation' => ['ROLLBACK', 'BULK_ROLLBACK']],
            ['createdAt' => 'DESC'],
            10
        );

        $rollbackStats['recent_activity'] = $recentActivity;

        return $rollbackStats;
    }

    /**
     * Get conflict details for an audit log.
     */
    private function getConflictDetails(AuditLog $auditLog): array
    {
        // This would contain detailed conflict information
        // For now, return a simple structure
        return [
            'type' => 'entity_modified',
            'description' => 'The entity has been modified since this audit log was created',
            'last_modified' => new \DateTime(), // This should be the actual last modification date
        ];
    }

    /**
     * Get entity display name.
     */
    private function getEntityDisplayName(string $entityClass, $entityId): string
    {
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
}
