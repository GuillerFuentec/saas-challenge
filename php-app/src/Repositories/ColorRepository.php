<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ColorRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function all(): array
    {
        $statement = $this->connection->query('SELECT id, name, hexadecimal FROM colors ORDER BY id ASC');
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT id, name, hexadecimal FROM colors WHERE id = :id');
        $statement->execute(['id' => $id]);
        $color = $statement->fetch();

        return $color ?: null;
    }

    public function create(string $name, string $hex): array
    {
        $statement = $this->connection->prepare('INSERT INTO colors (name, hexadecimal) VALUES (:name, :hexadecimal)');
        $statement->execute([
            'name' => $name,
            'hexadecimal' => strtoupper($hex)
        ]);

        $id = (int) $this->connection->lastInsertId();
        return [
            'id' => $id,
            'name' => $name,
            'hexadecimal' => strtoupper($hex)
        ];
    }

    public function update(int $id, array $payload): ?array
    {
        $fields = [];
        $params = ['id' => $id];

        if (array_key_exists('name', $payload)) {
            $fields[] = 'name = :name';
            $params['name'] = $payload['name'];
        }

        if (array_key_exists('hexadecimal', $payload)) {
            $fields[] = 'hexadecimal = :hexadecimal';
            $params['hexadecimal'] = strtoupper($payload['hexadecimal']);
        }

        if ($fields === []) {
            return $this->find($id);
        }

        $sql = 'UPDATE colors SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $statement = $this->connection->prepare('DELETE FROM colors WHERE id = :id');
        $statement->execute(['id' => $id]);
        return $statement->rowCount() > 0;
    }
}
