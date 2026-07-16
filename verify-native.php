<?php

declare(strict_types=1);

use pathfinder\NavMesh;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;

function check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

foreach (["ext_math", "ext_nbt", "pathfinder"] as $extension) {
    check(extension_loaded($extension), "Missing required extension: {$extension}");
}

foreach ([Vector3::class, CompoundTag::class, NavMesh::class] as $class) {
    check((new ReflectionClass($class))->isInternal(), "Expected internal class: {$class}");
}

// ext-math: arithmetic, floor helpers, public properties and subclass support.
$vector = new Vector3(1.25, 2.75, -3.5);
$sum = $vector->add(2, 3, 4);
check(abs($sum->x - 3.25) < 0.000001, 'Vector3::add() returned the wrong X value');
check(abs($sum->y - 5.75) < 0.000001, 'Vector3::add() returned the wrong Y value');
check(abs($sum->z - 0.5) < 0.000001, 'Vector3::add() returned the wrong Z value');
check($vector->getFloorX() === 1 && $vector->getFloorY() === 2 && $vector->getFloorZ() === -4, 'Vector3 floor helpers are incompatible');

class NativeVectorProbe extends Vector3 {}
$probe = new NativeVectorProbe(4.0, 5.0, 6.0);
check($probe->subtract(1, 2, 3)->equals(new Vector3(3.0, 3.0, 3.0)), 'Vector3 subclass dispatch is incompatible');

// ext-nbt: fluent mutation, typed reads, clone isolation and equality.
$tag = CompoundTag::create()
    ->setInt('count', 42)
    ->setString('name', 'rave')
    ->setFloat('speed', 1.5);
check($tag->getInt('count') === 42, 'CompoundTag integer round-trip failed');
check($tag->getString('name') === 'rave', 'CompoundTag string round-trip failed');
check(abs($tag->getFloat('speed') - 1.5) < 0.000001, 'CompoundTag float round-trip failed');

$clone = clone $tag;
check($clone->equals($tag), 'CompoundTag clone equality failed');
$clone->setInt('count', 99);
check($tag->getInt('count') === 42, 'CompoundTag clone mutated the original');
check(!$clone->equals($tag), 'CompoundTag equality did not observe a mutation');

// ext-pathfinder: load a native subchunk and solve an actual path.
$nav = new NavMesh();
$nav->setBlockProperties([
    0 => [true, false],
    1 => [false, true],
]);
$nav->loadSubChunk(0, 0, 0, str_repeat(pack('V', 1), 4096));
for ($x = 0; $x < 16; ++$x) {
    for ($z = 0; $z < 16; ++$z) {
        $nav->updateBlock($x, 15, $z, 0);
    }
}
$path = $nav->findPath(1, 15, 1, 10, 15, 10, [
    'entityWidth' => 1,
    'entityHeight' => 1,
    'allowDiagonal' => true,
    'maxIterations' => 5000,
    'useCache' => false,
]);
check($path !== null && count($path) >= 2, 'NavMesh failed to solve an open-field path');
check($path[0] === [1, 15, 1], 'NavMesh path start is incompatible');
check($path[array_key_last($path)] === [10, 15, 10], 'NavMesh path end is incompatible');

fwrite(STDOUT, "Native extension verification passed\n");
