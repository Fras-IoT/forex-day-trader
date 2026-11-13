<?php

require __DIR__ . '/vendor/autoload.php';

echo "=== MIGRATION: SERVER MONGODB -> LOCAL MONGODB ===\n\n";

// Target: Local MongoDB
$targetUri = 'mongodb://127.0.0.1:27017';
echo "[1] Menghubungkan ke Local MongoDB ({$targetUri})...\n";
$targetClient = new MongoDB\Client($targetUri);
$targetDb = $targetClient->selectDatabase('forex_day_trader');

// Sources: VPS (primary server with trade history) and Atlas (fallback/cloud)
$sources = [
    'VPS Server' => 'mongodb://100.124.118.37:27018',
    'Atlas Cloud' => 'mongodb+srv://frastiooktaviano_db_user:frastioganteng@cluster0.wyqxznx.mongodb.net/?retryWrites=true&w=majority&appName=Cluster0',
];

foreach ($sources as $sourceName => $sourceUri) {
    try {
        echo "\n[2] Menghubungkan ke {$sourceName}...\n";
        $sourceClient = new MongoDB\Client($sourceUri, [], ['serverSelectionTimeoutMS' => 5000]);
        $sourceDb = $sourceClient->selectDatabase('forex_day_trader');

        $collections = iterator_to_array($sourceDb->listCollections());
        echo "Ditemukan " . count($collections) . " collections di {$sourceName}.\n";

        foreach ($collections as $colInfo) {
            $name = $colInfo->getName();
            $sourceCol = $sourceDb->selectCollection($name);
            $targetCol = $targetDb->selectCollection($name);

            $cursor = $sourceCol->find([]);
            $count = 0;
            foreach ($cursor as $doc) {
                if (isset($doc['_id'])) {
                    $targetCol->replaceOne(['_id' => $doc['_id']], $doc, ['upsert' => true]);
                    $count++;
                }
            }
            echo " -> [{$sourceName}] {$name}: {$count} documents berhasil dimigrasi.\n";
        }
    } catch (\Throwable $e) {
        echo "Peringatan pada {$sourceName}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== RINGKASAN DATA DI LOCAL MONGODB (127.0.0.1:27017) ===\n";
foreach ($targetDb->listCollections() as $colInfo) {
    $name = $colInfo->getName();
    $cnt = $targetDb->selectCollection($name)->countDocuments();
    echo "  * {$name}: {$cnt} documents\n";
}

echo "\nMigrasi database server ke lokal selesai dengan sukses!\n";

