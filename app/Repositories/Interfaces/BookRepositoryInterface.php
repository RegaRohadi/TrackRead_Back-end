<?php

namespace App\Repositories\Interfaces;

interface BookRepositoryInterface
{
    public function all();

    public function paginate(int $perPage = 10, array $filters = []);

    public function genres(): array;

    public function find(int $id);

    public function create(array $data);

    public function update(int $id, array $data);

    public function delete(int $id);

    public function search(string $keyword, int $perPage = 10, array $filters = []);
}
