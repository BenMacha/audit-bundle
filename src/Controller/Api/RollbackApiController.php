<?php

namespace BenMacha\AuditBundle\Controller\Api;

use BenMacha\AuditBundle\Entity\AuditLog;
use BenMacha\AuditBundle\Repository\AuditLogRepository;
use BenMacha\AuditBundle\Service\RollbackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/rollback', name: 'api_rollback_')]
class RollbackApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditLogRepository $auditLogRepository,
        private RollbackService $rollbackService,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/preview/{id}', name: 'preview', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_ROLLBACK')]
    public function previewRollback(int $id): JsonResponse
    {
        $auditLog = $this->auditLogRepository->find($id);

        if (!$auditLog) {
            return new JsonResponse(['error' => 'Audit log not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $preview = $this->rollbackService->getRollbackPreview($auditLog);

            return new JsonResponse([
                'data' => [
                    'audit_log' => $this->serializeAuditLog($auditLog),
                    'preview' => $preview,
                    'can_rollback' => $this->rollbackService->canRollback($auditLog),
                    'conflicts' => $this->rollbackService->hasConflicts($auditLog),
                    'is_already_rolled_back' => $this->rollbackService->isAlreadyRolledBack($auditLog),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to generate rollback preview: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/execute/{id}', name: 'execute', methods: ['POST'])]
    #[IsGranted('ROLE_AUDIT_ROLLBACK')]
    public function executeRollback(int $id, Request $request): JsonResponse
    {
        $auditLog = $this->auditLogRepository->find($id);

        if (!$auditLog) {
            return new JsonResponse(['error' => 'Audit log not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $force = $data['force'] ?? false;
        $reason = $data['reason'] ?? null;

        // Check if rollback is possible
        if (!$this->rollbackService->canRollback($auditLog)) {
            return new JsonResponse([
                'error' => 'Rollback not possible for this audit log',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check for conflicts unless forced
        if (!$force && $this->rollbackService->hasConflicts($auditLog)) {
            return new JsonResponse([
                'error' => 'Conflicts detected. Use force=true to override.',
                'conflicts' => $this->rollbackService->getConflictDetails($auditLog),
            ], Response::HTTP_CONFLICT);
        }

        // Check if already rolled back
        if ($this->rollbackService->isAlreadyRolledBack($auditLog)) {
            return new JsonResponse([
                'error' => 'This audit log has already been rolled back',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->rollbackService->rollbackAuditLog($auditLog, $reason);

            return new JsonResponse([
                'message' => 'Rollback executed successfully',
                'data' => [
                    'rollback_id' => $result['rollback_id'] ?? null,
                    'affected_records' => $result['affected_records'] ?? 0,
                    'operation_type' => $result['operation_type'] ?? null,
                    'executed_at' => (new \DateTime())->format('c'),
                    'reason' => $reason,
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Rollback failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/bulk/preview', name: 'bulk_preview', methods: ['POST'])]
    #[IsGranted('ROLE_AUDIT_ROLLBACK')]
    public function previewBulkRollback(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['audit_log_ids']) || !is_array($data['audit_log_ids'])) {
            return new JsonResponse(['error' => 'audit_log_ids array is required'], Response::HTTP_BAD_REQUEST);
        }

        $auditLogIds = $data['audit_log_ids'];
        $auditLogs = $this->auditLogRepository->findBy(['id' => $auditLogIds]);

        if (count($auditLogs) !== count($auditLogIds)) {
            return new JsonResponse(['error' => 'Some audit logs not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $previews = [];
            $canRollbackAll = true;
            $hasConflicts = false;
            $alreadyRolledBack = [];

            foreach ($auditLogs as $auditLog) {
                $preview = $this->rollbackService->getRollbackPreview($auditLog);
                $canRollback = $this->rollbackService->canRollback($auditLog);
                $conflicts = $this->rollbackService->hasConflicts($auditLog);
                $isRolledBack = $this->rollbackService->isAlreadyRolledBack($auditLog);

                $previews[] = [
                    'audit_log' => $this->serializeAuditLog($auditLog),
                    'preview' => $preview,
                    'can_rollback' => $canRollback,
                    'has_conflicts' => $conflicts,
                    'is_already_rolled_back' => $isRolledBack,
                ];

                if (!$canRollback) {
                    $canRollbackAll = false;
                }
                if ($conflicts) {
                    $hasConflicts = true;
                }
                if ($isRolledBack) {
                    $alreadyRolledBack[] = $auditLog->getId();
                }
            }

            return new JsonResponse([
                'data' => [
                    'previews' => $previews,
                    'summary' => [
                        'total_logs' => count($auditLogs),
                        'can_rollback_all' => $canRollbackAll,
                        'has_conflicts' => $hasConflicts,
                        'already_rolled_back' => $alreadyRolledBack,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to generate bulk rollback preview: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/bulk/execute', name: 'bulk_execute', methods: ['POST'])]
    #[IsGranted('ROLE_AUDIT_ROLLBACK')]
    public function executeBulkRollback(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['audit_log_ids']) || !is_array($data['audit_log_ids'])) {
            return new JsonResponse(['error' => 'audit_log_ids array is required'], Response::HTTP_BAD_REQUEST);
        }

        $auditLogIds = $data['audit_log_ids'];
        $force = $data['force'] ?? false;
        $reason = $data['reason'] ?? null;
        $stopOnError = $data['stop_on_error'] ?? true;

        $auditLogs = $this->auditLogRepository->findBy(['id' => $auditLogIds]);

        if (count($auditLogs) !== count($auditLogIds)) {
            return new JsonResponse(['error' => 'Some audit logs not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $results = $this->rollbackService->rollbackMultiple($auditLogs, $reason, $force, $stopOnError);

            return new JsonResponse([
                'message' => 'Bulk rollback completed',
                'data' => [
                    'total_requested' => count($auditLogIds),
                    'successful' => $results['successful'] ?? 0,
                    'failed' => $results['failed'] ?? 0,
                    'skipped' => $results['skipped'] ?? 0,
                    'results' => $results['details'] ?? [],
                    'executed_at' => (new \DateTime())->format('c'),
                    'reason' => $reason,
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Bulk rollback failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/entity/{entityClass}/{entityId}/to-date', name: 'entity_to_date', methods: ['POST'])]
    #[IsGranted('ROLE_AUDIT_ROLLBACK')]
    public function rollbackEntityToDate(string $entityClass, string $entityId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['target_date'])) {
            return new JsonResponse(['error' => 'target_date is required'], Response::HTTP_BAD_REQUEST);
        }

        $decodedEntityClass = base64_decode($entityClass);
        if (!$decodedEntityClass || !class_exists($decodedEntityClass)) {
            return new JsonResponse(['error' => 'Invalid entity class'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $targetDate = new \DateTime($data['target_date']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid target_date format'], Response::HTTP_BAD_REQUEST);
        }

        $reason = $data['reason'] ?? null;
        $force = $data['force'] ?? false;

        try {
            $result = $this->rollbackService->rollbackEntityToDate(
                $decodedEntityClass,
                $entityId,
                $targetDate,
                $reason,
                $force
            );

            return new JsonResponse([
                'message' => 'Entity rollback to date completed successfully',
                'data' => [
                    'entity_class' => $decodedEntityClass,
                    'entity_id' => $entityId,
                    'target_date' => $targetDate->format('c'),
                    'rollbacks_applied' => $result['rollbacks_applied'] ?? 0,
                    'final_state' => $result['final_state'] ?? null,
                    'executed_at' => (new \DateTime())->format('c'),
                    'reason' => $reason,
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Entity rollback to date failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function getRollbackHistory(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $entityClass = $request->query->get('entity_class');
        $entityId = $request->query->get('entity_id');
        $userId = $request->query->get('user_id');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');

        try {
            $history = $this->rollbackService->getRollbackHistory(
                $limit,
                $offset,
                $entityClass,
                $entityId,
                $userId,
                $dateFrom,
                $dateTo
            );

            $total = $this->rollbackService->countRollbackHistory(
                $entityClass,
                $entityId,
                $userId,
                $dateFrom,
                $dateTo
            );

            $totalPages = ceil($total / $limit);

            return new JsonResponse([
                'data' => $history,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1,
                ],
                'filters' => [
                    'entity_class' => $entityClass,
                    'entity_id' => $entityId,
                    'user_id' => $userId,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to retrieve rollback history: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/history/{id}', name: 'history_detail', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function getRollbackHistoryDetail(int $id): JsonResponse
    {
        try {
            $rollbackDetail = $this->rollbackService->getRollbackDetail($id);

            if (!$rollbackDetail) {
                return new JsonResponse(['error' => 'Rollback history not found'], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse([
                'data' => $rollbackDetail,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to retrieve rollback detail: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/conflicts/{id}', name: 'check_conflicts', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function checkConflicts(int $id): JsonResponse
    {
        $auditLog = $this->auditLogRepository->find($id);

        if (!$auditLog) {
            return new JsonResponse(['error' => 'Audit log not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $hasConflicts = $this->rollbackService->hasConflicts($auditLog);
            $conflictDetails = $hasConflicts ? $this->rollbackService->getConflictDetails($auditLog) : null;

            return new JsonResponse([
                'data' => [
                    'audit_log_id' => $id,
                    'has_conflicts' => $hasConflicts,
                    'conflict_details' => $conflictDetails,
                    'can_rollback' => $this->rollbackService->canRollback($auditLog),
                    'is_already_rolled_back' => $this->rollbackService->isAlreadyRolledBack($auditLog),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to check conflicts: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_VIEW')]
    public function getRollbackStatistics(Request $request): JsonResponse
    {
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');
        $entityClass = $request->query->get('entity_class');
        $userId = $request->query->get('user_id');

        try {
            $statistics = $this->rollbackService->getRollbackStatistics(
                $dateFrom,
                $dateTo,
                $entityClass,
                $userId
            );

            return new JsonResponse([
                'data' => $statistics,
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'entity_class' => $entityClass,
                    'user_id' => $userId,
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to retrieve rollback statistics: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function serializeAuditLog(AuditLog $auditLog): array
    {
        return [
            'id' => $auditLog->getId(),
            'entity_class' => $auditLog->getEntityClass(),
            'entity_id' => $auditLog->getEntityId(),
            'operation' => $auditLog->getOperation(),
            'user_id' => $auditLog->getUserId(),
            'username' => $auditLog->getUsername(),
            'ip_address' => $auditLog->getIpAddress(),
            'user_agent' => $auditLog->getUserAgent(),
            'created_at' => $auditLog->getCreatedAt()->format('c'),
            'old_values' => $auditLog->getOldValues(),
            'new_values' => $auditLog->getNewValues(),
            'metadata' => $auditLog->getMetadata(),
        ];
    }
}
