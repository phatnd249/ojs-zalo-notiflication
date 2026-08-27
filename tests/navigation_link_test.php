<?php

define('ROUTE_PAGE', 1);

function assertTrue($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

class ZaloNotificationPlugin
{
    public static function writeSecureDebug(string $message, string $component = ''): void
    {
    }
}

class LinkTestContext
{
    private $id;
    private $path;

    public function __construct(int $id, string $path)
    {
        $this->id = $id;
        $this->path = $path;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}

class LinkTestRequest
{
    public function getContext(): LinkTestContext
    {
        return new LinkTestContext(1, 'wrong-journal');
    }
}

class LinkTestDispatcher
{
    public function url($request, $route, $contextPath, $page, $operation, $path = null, $params = []): string
    {
        $pathParts = is_array($path) ? $path : ($path === null ? [] : [$path]);
        $suffix = empty($pathParts) ? '' : '/' . implode('/', $pathParts);
        return 'https://ojs.test/' . $contextPath . '/' . $page . '/' . $operation . $suffix;
    }
}

class Application
{
    private static $instance;

    public static function get(): Application
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getRequest(): LinkTestRequest
    {
        return new LinkTestRequest();
    }

    public function getDispatcher(): LinkTestDispatcher
    {
        return new LinkTestDispatcher();
    }
}

class DAORegistry
{
    public static function getDAO($name)
    {
        return new class {
            public function getById($id): LinkTestContext
            {
                return new LinkTestContext((int) $id, 'journal-correct');
            }
        };
    }
}

class LinkTestPublication
{
    public function getId(): int
    {
        return 654;
    }
}

class LinkTestSubmission
{
    public function getId(): int
    {
        return 321;
    }

    public function getStageId(): int
    {
        return 3;
    }

    public function getContextId(): int
    {
        return 77;
    }

    public function getData($key)
    {
        return $key === 'contextId' ? 77 : null;
    }

    public function getCurrentPublication(): LinkTestPublication
    {
        return new LinkTestPublication();
    }
}

require_once dirname(__DIR__) . '/MessageHelper.inc.php';

$data = MessageHelper::getNavigationData(new LinkTestSubmission(), 0, 3);
assertTrue(
    $data['authorUrl'] === 'https://ojs.test/journal-correct/authorDashboard/submission/321',
    'Link tác giả không trỏ đúng journal và submission: ' . $data['authorUrl']
);
assertTrue($data['submissionId'] === '321', 'Sai submissionId trong dữ liệu điều hướng.');
assertTrue($data['publicationId'] === '654', 'Sai publicationId trong dữ liệu điều hướng.');

echo "Navigation link test passed.\n";
