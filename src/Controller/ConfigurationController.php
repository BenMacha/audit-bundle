<?php

namespace BenMacha\AuditBundle\Controller;

use BenMacha\AuditBundle\Entity\EntityConfig;
use BenMacha\AuditBundle\Repository\AuditConfigRepository;
use BenMacha\AuditBundle\Repository\EntityConfigRepository;
use BenMacha\AuditBundle\Service\AuditManager;
use BenMacha\AuditBundle\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/audit/config', name: 'audit_config_')]
class ConfigurationController extends AbstractController
{
    private AuditConfigRepository $auditConfigRepository;
    private EntityConfigRepository $entityConfigRepository;
    private ConfigurationService $configurationService;
    private AuditManager $auditManager;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;

    public function __construct(
        AuditConfigRepository $auditConfigRepository,
        EntityConfigRepository $entityConfigRepository,
        ConfigurationService $configurationService,
        AuditManager $auditManager,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ) {
        $this->auditConfigRepository = $auditConfigRepository;
        $this->entityConfigRepository = $entityConfigRepository;
        $this->configurationService = $configurationService;
        $this->auditManager = $auditManager;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
    }

    #[Route('/', name: 'index')]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function index(): Response
    {
        // Get global configuration
        $globalConfig = $this->configurationService->getGlobalAuditConfig();

        // Get entity configurations with statistics
        $entityConfigs = $this->entityConfigRepository->findWithStatistics();

        // Get system statistics
        $systemStats = $this->auditManager->getAuditStatistics();

        // Get available entity classes
        $availableEntities = $this->getAvailableEntityClasses();

        return $this->render('@Audit/config/index.html.twig', [
            'global_config' => $globalConfig,
            'entity_configs' => $entityConfigs,
            'system_stats' => $systemStats,
            'available_entities' => $availableEntities,
        ]);
    }

    #[Route('/global', name: 'global')]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function globalConfig(Request $request): Response
    {
        $config = $this->configurationService->getGlobalAuditConfig();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Update global settings
            $settings = [
                'enabled' => isset($data['enabled']),
                'retention_days' => (int) ($data['retention_days'] ?? 365),
                'async_processing' => isset($data['async_processing']),
                'database_connection' => $data['database_connection'] ?? 'default',
                'api_enabled' => isset($data['api_enabled']),
                'api_rate_limit' => (int) ($data['api_rate_limit'] ?? 100),
                'api_prefix' => $data['api_prefix'] ?? '/api/audit',
                'security_admin_role' => $data['security_admin_role'] ?? 'ROLE_AUDIT_ADMIN',
                'security_auditor_role' => $data['security_auditor_role'] ?? 'ROLE_AUDIT_AUDITOR',
                'security_developer_role' => $data['security_developer_role'] ?? 'ROLE_AUDIT_DEVELOPER',
                'ui_route_prefix' => $data['ui_route_prefix'] ?? '/audit',
                'ui_items_per_page' => (int) ($data['ui_items_per_page'] ?? 25),
                'ui_show_ip_addresses' => isset($data['ui_show_ip_addresses']),
            ];

            $config->setSettings(json_encode($settings));
            $config->setUpdatedAt(new \DateTime());

            $this->entityManager->flush();

            $this->addFlash('success', 'Global configuration updated successfully.');

            return $this->redirectToRoute('audit_config_global');
        }

        // Parse current settings
        $settings = json_decode($config->getSettings() ?? '{}', true);

        return $this->render('@Audit/config/global.html.twig', [
            'config' => $config,
            'settings' => $settings,
        ]);
    }

    #[Route('/entities', name: 'entities')]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function entities(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Get entity configurations with pagination
        $entityConfigs = $this->entityConfigRepository->findWithStatistics($limit, $offset);
        $totalCount = $this->entityConfigRepository->count([]);
        $totalPages = ceil($totalCount / $limit);

        // Get available entity classes not yet configured
        $availableEntities = $this->getAvailableEntityClasses();
        $configuredEntities = array_map(fn ($config) => $config->getEntityClass(), $entityConfigs);
        $unconfiguredEntities = array_diff($availableEntities, $configuredEntities);

        return $this->render('@Audit/config/entities.html.twig', [
            'entity_configs' => $entityConfigs,
            'unconfigured_entities' => $unconfiguredEntities,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
        ]);
    }

    #[Route('/entity/{id}', name: 'entity_edit', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function editEntity(Request $request, EntityConfig $entityConfig): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Update entity configuration
            $entityConfig->setEnabled(isset($data['enabled']));
            $entityConfig->setCreateTable(isset($data['create_table']));
            $entityConfig->setTableName($data['table_name'] ?? null);

            // Update ignored columns
            $ignoredColumns = array_filter(explode(',', $data['ignored_columns'] ?? ''));
            $entityConfig->setIgnoredColumns(array_map('trim', $ignoredColumns));

            // Update settings
            $settings = [
                'track_relations' => isset($data['track_relations']),
                'track_collections' => isset($data['track_collections']),
                'custom_serializers' => $data['custom_serializers'] ?? [],
                'metadata_collectors' => $data['metadata_collectors'] ?? [],
            ];
            $entityConfig->setSettings(json_encode($settings));
            $entityConfig->setUpdatedAt(new \DateTime());

            // Create audit table if requested
            if ($entityConfig->isCreateTable()) {
                try {
                    $this->auditManager->createAuditTable($entityConfig->getEntityClass());
                    $this->addFlash('success', 'Entity configuration updated and audit table created.');
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Entity configuration updated but audit table creation failed: ' . $e->getMessage());
                }
            } else {
                $this->addFlash('success', 'Entity configuration updated successfully.');
            }

            $this->entityManager->flush();

            return $this->redirectToRoute('audit_config_entity_edit', ['id' => $entityConfig->getId()]);
        }

        // Get entity metadata
        $entityMetadata = $this->getEntityMetadata($entityConfig->getEntityClass());

        // Parse current settings
        $settings = json_decode($entityConfig->getSettings() ?? '{}', true);

        return $this->render('@Audit/config/entity_edit.html.twig', [
            'entity_config' => $entityConfig,
            'entity_metadata' => $entityMetadata,
            'settings' => $settings,
        ]);
    }

    #[Route('/entity/new', name: 'entity_new')]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function newEntity(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $entityClass = $data['entity_class'] ?? '';

            if (empty($entityClass)) {
                $this->addFlash('error', 'Entity class is required.');

                return $this->redirectToRoute('audit_config_entity_new');
            }

            // Check if entity already configured
            $existingConfig = $this->entityConfigRepository->findByEntityClass($entityClass);
            if ($existingConfig) {
                $this->addFlash('error', 'Entity is already configured.');

                return $this->redirectToRoute('audit_config_entity_edit', ['id' => $existingConfig->getId()]);
            }

            // Create new entity configuration
            $entityConfig = new EntityConfig();
            $entityConfig->setEntityClass($entityClass);
            $entityConfig->setEnabled(isset($data['enabled']));
            $entityConfig->setCreateTable(isset($data['create_table']));
            $entityConfig->setTableName($data['table_name'] ?? null);

            // Set ignored columns
            $ignoredColumns = array_filter(explode(',', $data['ignored_columns'] ?? ''));
            $entityConfig->setIgnoredColumns(array_map('trim', $ignoredColumns));

            // Set default settings
            $settings = [
                'track_relations' => isset($data['track_relations']),
                'track_collections' => isset($data['track_collections']),
                'custom_serializers' => [],
                'metadata_collectors' => [],
            ];
            $entityConfig->setSettings(json_encode($settings));

            // Get global config
            $globalConfig = $this->configurationService->getGlobalAuditConfig();
            $entityConfig->setAuditConfig($globalConfig);

            $this->entityManager->persist($entityConfig);

            // Create audit table if requested
            if ($entityConfig->isCreateTable()) {
                try {
                    $this->auditManager->createAuditTable($entityClass);
                    $this->addFlash('success', 'Entity configuration created and audit table created.');
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Entity configuration created but audit table creation failed: ' . $e->getMessage());
                }
            } else {
                $this->addFlash('success', 'Entity configuration created successfully.');
            }

            $this->entityManager->flush();

            return $this->redirectToRoute('audit_config_entity_edit', ['id' => $entityConfig->getId()]);
        }

        // Get available entity classes
        $availableEntities = $this->getAvailableEntityClasses();
        $configuredEntities = $this->entityConfigRepository->createQueryBuilder('ec')
            ->select('ec.entityClass')
            ->getQuery()
            ->getSingleColumnResult();
        $unconfiguredEntities = array_diff($availableEntities, $configuredEntities);

        return $this->render('@Audit/config/entity_new.html.twig', [
            'unconfigured_entities' => $unconfiguredEntities,
        ]);
    }

    #[Route('/entity/{id}/delete', name: 'entity_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function deleteEntity(EntityConfig $entityConfig): Response
    {
        $entityClass = $entityConfig->getEntityClass();

        $this->entityManager->remove($entityConfig);
        $this->entityManager->flush();

        $this->addFlash('success', "Configuration for {$entityClass} has been deleted.");

        return $this->redirectToRoute('audit_config_entities');
    }

    #[Route('/export', name: 'export')]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function export(): Response
    {
        $configuration = $this->configurationService->exportConfiguration();

        $response = new Response(json_encode($configuration, JSON_PRETTY_PRINT));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="audit_config_' . date('Y-m-d_H-i-s') . '.json"');

        return $response;
    }

    #[Route('/import', name: 'import')]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function import(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            /** @var UploadedFile $file */
            $file = $request->files->get('config_file');

            if (!$file) {
                $this->addFlash('error', 'Please select a configuration file.');

                return $this->redirectToRoute('audit_config_import');
            }

            if ('json' !== $file->getClientOriginalExtension()) {
                $this->addFlash('error', 'Configuration file must be a JSON file.');

                return $this->redirectToRoute('audit_config_import');
            }

            try {
                $content = file_get_contents($file->getPathname());
                $configuration = json_decode($content, true);

                if (JSON_ERROR_NONE !== json_last_error()) {
                    throw new \Exception('Invalid JSON format: ' . json_last_error_msg());
                }

                $overwrite = $request->request->getBoolean('overwrite', false);
                $result = $this->configurationService->importConfiguration($configuration, $overwrite);

                $this->addFlash('success', "Configuration imported successfully. {$result['imported']} configurations imported, {$result['skipped']} skipped.");
            } catch (\Exception $e) {
                $this->addFlash('error', 'Import failed: ' . $e->getMessage());
            }

            return $this->redirectToRoute('audit_config_import');
        }

        return $this->render('@Audit/config/import.html.twig');
    }

    #[Route('/test', name: 'test')]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function test(): Response
    {
        $testResults = [];

        // Test database connection
        try {
            $this->entityManager->getConnection()->connect();
            $testResults['database'] = ['status' => 'success', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            $testResults['database'] = ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }

        // Test audit configuration
        try {
            $config = $this->configurationService->getGlobalAuditConfig();
            $testResults['config'] = ['status' => 'success', 'message' => 'Audit configuration loaded successfully'];
        } catch (\Exception $e) {
            $testResults['config'] = ['status' => 'error', 'message' => 'Configuration loading failed: ' . $e->getMessage()];
        }

        // Test entity discovery
        try {
            $entities = $this->getAvailableEntityClasses();
            $testResults['entities'] = ['status' => 'success', 'message' => count($entities) . ' entities discovered'];
        } catch (\Exception $e) {
            $testResults['entities'] = ['status' => 'error', 'message' => 'Entity discovery failed: ' . $e->getMessage()];
        }

        // Test audit manager
        try {
            $stats = $this->auditManager->getAuditStatistics();
            $testResults['audit_manager'] = ['status' => 'success', 'message' => 'Audit manager working correctly'];
        } catch (\Exception $e) {
            $testResults['audit_manager'] = ['status' => 'error', 'message' => 'Audit manager test failed: ' . $e->getMessage()];
        }

        return $this->render('@Audit/config/test.html.twig', [
            'test_results' => $testResults,
        ]);
    }

    #[Route('/ajax/entity-metadata', name: 'ajax_entity_metadata', methods: ['GET'])]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function ajaxEntityMetadata(Request $request): JsonResponse
    {
        $entityClass = $request->query->get('entity_class');

        if (!$entityClass) {
            return $this->json(['error' => 'Entity class is required'], 400);
        }

        try {
            $metadata = $this->getEntityMetadata($entityClass);

            return $this->json($metadata);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/ajax/bulk-update', name: 'ajax_bulk_update', methods: ['POST'])]
    #[IsGranted('ROLE_AUDIT_ADMIN')]
    public function ajaxBulkUpdate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? '';
        $entityIds = $data['entity_ids'] ?? [];

        if (empty($action) || empty($entityIds)) {
            return $this->json(['error' => 'Action and entity IDs are required'], 400);
        }

        try {
            $updated = 0;

            switch ($action) {
                case 'enable':
                    $updated = $this->entityConfigRepository->bulkUpdate($entityIds, ['enabled' => true]);
                    break;
                case 'disable':
                    $updated = $this->entityConfigRepository->bulkUpdate($entityIds, ['enabled' => false]);
                    break;
                case 'create_tables':
                    foreach ($entityIds as $id) {
                        $entityConfig = $this->entityConfigRepository->find($id);
                        if ($entityConfig) {
                            $this->auditManager->createAuditTable($entityConfig->getEntityClass());
                            ++$updated;
                        }
                    }
                    break;
                default:
                    return $this->json(['error' => 'Invalid action'], 400);
            }

            return $this->json(['success' => true, 'updated' => $updated]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get available entity classes from Doctrine.
     */
    private function getAvailableEntityClasses(): array
    {
        $metadataFactory = $this->entityManager->getMetadataFactory();
        $allMetadata = $metadataFactory->getAllMetadata();

        $entityClasses = [];
        foreach ($allMetadata as $metadata) {
            $entityClasses[] = $metadata->getName();
        }

        sort($entityClasses);

        return $entityClasses;
    }

    /**
     * Get entity metadata information.
     */
    private function getEntityMetadata(string $entityClass): array
    {
        $metadata = $this->entityManager->getClassMetadata($entityClass);

        $fields = [];
        foreach ($metadata->getFieldNames() as $fieldName) {
            $fields[] = [
                'name' => $fieldName,
                'type' => $metadata->getTypeOfField($fieldName),
                'nullable' => $metadata->isNullable($fieldName),
                'unique' => $metadata->isUniqueField($fieldName),
            ];
        }

        $associations = [];
        foreach ($metadata->getAssociationNames() as $associationName) {
            $associations[] = [
                'name' => $associationName,
                'type' => $metadata->getAssociationMapping($associationName)['type'],
                'target_entity' => $metadata->getAssociationTargetClass($associationName),
            ];
        }

        return [
            'table_name' => $metadata->getTableName(),
            'identifier' => $metadata->getIdentifierFieldNames(),
            'fields' => $fields,
            'associations' => $associations,
        ];
    }
}
