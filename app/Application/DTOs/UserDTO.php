<?php
/**
 * @file UserDTO.php
 * @responsibilidade Data Transfer Object para Usuários
 * @descrição Transporta dados de usuários entre camadas
 * @conexão Usado por UseCases, Controllers e Services
 */

namespace App\Application\DTOs;

class UserDTO {
    private ?int $id;
    private string $name;
    private string $email;
    private string $password;
    private ?string $role;
    private ?string $phone;
    private ?string $cpf;
    private ?string $birthDate;
    private ?string $address;
    private ?string $number;
    private ?string $neighborhood;
    private ?string $city;
    private ?string $state;
    private ?string $zipCode;
    private ?bool $active;
    private ?\DateTime $createdAt;
    private ?\DateTime $updatedAt;

    public function __construct(
        string $name,
        string $email,
        string $password,
        ?string $role = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->role = $role;
    }

    // Getters
    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getEmail(): string {
        return $this->email;
    }

    public function getPassword(): string {
        return $this->password;
    }

    public function getRole(): ?string {
        return $this->role;
    }

    public function getPhone(): ?string {
        return $this->phone;
    }

    public function getCpf(): ?string {
        return $this->cpf;
    }

    public function getBirthDate(): ?string {
        return $this->birthDate;
    }

    public function getAddress(): ?string {
        return $this->address;
    }

    public function getNumber(): ?string {
        return $this->number;
    }

    public function getNeighborhood(): ?string {
        return $this->neighborhood;
    }

    public function getCity(): ?string {
        return $this->city;
    }

    public function getState(): ?string {
        return $this->state;
    }

    public function getZipCode(): ?string {
        return $this->zipCode;
    }

    public function isActive(): ?bool {
        return $this->active;
    }

    public function getCreatedAt(): ?\DateTime {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime {
        return $this->updatedAt;
    }

    // Setters
    public function setId(?int $id): void {
        $this->id = $id;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function setEmail(string $email): void {
        $this->email = $email;
    }

    public function setPassword(string $password): void {
        $this->password = $password;
    }

    public function setRole(?string $role): void {
        $this->role = $role;
    }

    public function setPhone(?string $phone): void {
        $this->phone = $phone;
    }

    public function setCpf(?string $cpf): void {
        $this->cpf = $cpf;
    }

    public function setBirthDate(?string $birthDate): void {
        $this->birthDate = $birthDate;
    }

    public function setAddress(?string $address): void {
        $this->address = $address;
    }

    public function setNumber(?string $number): void {
        $this->number = $number;
    }

    public function setNeighborhood(?string $neighborhood): void {
        $this->neighborhood = $neighborhood;
    }

    public function setCity(?string $city): void {
        $this->city = $city;
    }

    public function setState(?string $state): void {
        $this->state = $state;
    }

    public function setZipCode(?string $zipCode): void {
        $this->zipCode = $zipCode;
    }

    public function setActive(?bool $active): void {
        $this->active = $active;
    }

    public function setCreatedAt(?\DateTime $createdAt): void {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void {
        $this->updatedAt = $updatedAt;
    }

    // Métodos de conveniência
    public function hasPhone(): bool {
        return !empty($this->phone);
    }

    public function hasCpf(): bool {
        return !empty($this->cpf);
    }

    public function hasBirthDate(): bool {
        return !empty($this->birthDate);
    }

    public function hasAddress(): bool {
        return !empty($this->address);
    }

    public function getFullAddress(): ?string {
        if (!$this->hasAddress()) {
            return null;
        }

        $parts = array_filter([
            $this->address,
            $this->number,
            $this->neighborhood,
            $this->city,
            $this->state,
            $this->zipCode
        ]);

        return implode(', ', $parts);
    }

    public function getAge(): ?int {
        if (!$this->hasBirthDate()) {
            return null;
        }

        $birthDate = new \DateTime($this->birthDate);
        $now = new \DateTime();
        return $now->diff($birthDate)->y;
    }

    // Métodos estáticos de criação
    public static function fromArray(array $data): self {
        $dto = new self(
            $data['name'] ?? '',
            $data['email'] ?? '',
            $data['password'] ?? '',
            $data['role'] ?? null,
            $data['id'] ?? null
        );

        $dto->phone = $data['phone'] ?? null;
        $dto->cpf = $data['cpf'] ?? null;
        $dto->birthDate = $data['birth_date'] ?? null;
        $dto->address = $data['address'] ?? null;
        $dto->number = $data['number'] ?? null;
        $dto->neighborhood = $data['neighborhood'] ?? null;
        $dto->city = $data['city'] ?? null;
        $dto->state = $data['state'] ?? null;
        $dto->zipCode = $data['zip_code'] ?? null;
        $dto->active = isset($data['active']) ? (bool) $data['active'] : null;
        $dto->createdAt = isset($data['created_at']) ? new \DateTime($data['created_at']) : null;
        $dto->updatedAt = isset($data['updated_at']) ? new \DateTime($data['updated_at']) : null;

        return $dto;
    }

    public static function fromRequest(array $requestData): self {
        return self::fromArray([
            'name' => trim($requestData['name'] ?? ''),
            'email' => strtolower(trim($requestData['email'] ?? '')),
            'password' => $requestData['password'] ?? '',
            'role' => $requestData['role'] ?? null,
            'phone' => preg_replace('/[^0-9]/', '', $requestData['phone'] ?? ''),
            'cpf' => preg_replace('/[^0-9]/', '', $requestData['cpf'] ?? ''),
            'birth_date' => $requestData['birth_date'] ?? null,
            'address' => trim($requestData['address'] ?? ''),
            'number' => trim($requestData['number'] ?? ''),
            'neighborhood' => trim($requestData['neighborhood'] ?? ''),
            'city' => trim($requestData['city'] ?? ''),
            'state' => strtoupper(trim($requestData['state'] ?? '')),
            'zip_code' => preg_replace('/[^0-9]/', '', $requestData['zip_code'] ?? '')
        ]);
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password, // Cuidado: nunca expor em respostas
            'role' => $this->role,
            'phone' => $this->phone,
            'cpf' => $this->cpf,
            'birth_date' => $this->birthDate,
            'address' => $this->address,
            'number' => $this->number,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
            'active' => $this->active,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }

    public function toSafeArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'phone' => $this->phone,
            'cpf' => $this->cpf ? substr($this->cpf, 0, 3) . '***' . substr($this->cpf, -2) : null,
            'birth_date' => $this->birthDate,
            'address' => $this->address,
            'number' => $this->number,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
            'active' => $this->active,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }
}
