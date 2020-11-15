<?php

// TODO PHP7.x; declare(strict_type=1);
// TODO PHP7.x; type hints & return types
// TODO PHP7.1; constant visibility

namespace WPStaging\Framework\Filesystem;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use WPStaging\Framework\Queue\FinishedQueueException;
use WPStaging\Framework\Queue\Queue;
use WPStaging\Framework\Queue\Storage\BufferedCacheStorage;
use WPStaging\Framework\Utils\Cache\BufferedCache;

class FileScanner
{
    const DATA_CACHE_FILE = 'filesystem_scanner_file_data';
    const QUEUE_CACHE_FILE = 'file_scanner';

    /** @var BufferedCache */
    private $cache;

    /** @var BufferedCacheStorage */
    private $storage;

    /** @var DirectoryService */
    private $service;

    /** @var Queue|null */
    private $queue;

    /** @var array */
    private $newQueueItems;

    public function __construct(BufferedCache $cache, BufferedCacheStorage $storage, FileService $service)
    {
        $this->newQueueItems = [];
        $this->cache = clone $cache;
        $this->storage = clone $storage;
        $this->service = $service;
    }

    public function __destruct()
    {
        if ($this->newQueueItems && $this->queue) {
            $this->queue->pushAsArray($this->newQueueItems);
        }
    }

    /**
     * @param string $name
     */
    public function setQueueByName($name = self::QUEUE_CACHE_FILE)
    {
        $this->queue = new Queue;
        $this->queue->setName($name);
        $this->queue->setStorage($this->storage);
    }

    /**
     * @param array|null $excluded
     * @param int $depth
     * @return Finder|null
     */
    public function scanCurrentPath(array $excluded = null, $depth = 0)
    {
        $path = $this->getPathFromQueue();
        if (null === $path) {
            throw new FinishedQueueException('File Scanner Queue is Finished');
        }

        $path = ABSPATH . $path;
        try {
            return $this->service->scan($path, $depth, $excluded);
        } catch(InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * @return string|null
     */
    public function getPathFromQueue()
    {
        if (0 < $this->queue->count()) {
            return $this->queue->pop();
        }

        if ($this->newQueueItems) {
            return array_shift($this->newQueueItems);
        }

        return null;
    }

    /**
     * @param string $item
     */
    public function addToNewQueue($item)
    {
        $this->newQueueItems[] = $item;
    }

    /**
     * @return BufferedCache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @return Queue
     */
    public function getQueue()
    {
        if (!$this->queue) {
            // TODO Custom Exception
            throw new RuntimeException('FileScanner Queue is not set');
        }
        return $this->queue;
    }

    public function setNewQueueItems(array $items = null)
    {
        $this->newQueueItems = $items;
    }
}
