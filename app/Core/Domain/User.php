<?php
/**
 * @file User.php
 * @responsabilidade Entidade de Domínio para Usuários
 * @descrição Representa um usuário no sistema com todas as suas regras de negócio
 * @conexão Usada por Services, Repositories e Controllers
 */

namespace App\Core\Domain;

use App\Core\ValueObjects\Email;
use App\Core\ValueObjects\Phone;
use App\Shared\Constants\UserRoles;

class User {
    private ?int $id;
    private string $name;
    private Email $email;
    private string $password;
    private ?Phone $phone;
    private ?string $cpf;
    private ?string $birthDate;
    private ?string $address;
    private ?string $number;
    private ?string $neighborhood;
    private ?string $city;
    private ?string $state;
    private ?string $zipCode;
    private string $role;
    private bool $active;
    private ?\DateTime $createdAt;
    private ?\DateTime $updatedAt;

    public function __construct(
        string $name,
        Email $email,
        string $password,
        string $role = UserRoles::CLIENT,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->name = $this->validateName($name);
        $this->email = $email;
        $this->password = $this->hashPassword($password);
        $this->role = $this->validateRole($role);
        $this->active = true;
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // Getters
    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getEmail(): Email {
        return $this->email;
    }

    public function getPassword(): string {
        return $this->password;
    }

    public function getPhone(): ?Phone {
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

    public function getRole(): string {
        return $this->role;
    }

    public function isActive(): bool {
        return $this->active;
    }

    public function getCreatedAt(): ?\DateTime {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime {
        return $this->updatedAt;
    }

    // Setters com validação
    public function setName(string $name): void {
        $this->name = $this->validateName($name);
        $this->updatedAt = new \DateTime();
    }

    public function setEmail(Email $email): void {
        $this->email = $email;
        $this->updatedAt = new \DateTime();
    }

    public function setPassword(string $password): void {
        $this->password = $this->hashPassword($password);
        $this->updatedAt = new \DateTime();
    }

    public function setPhone(?Phone $phone): void {
        $this->phone = $phone;
        $this->updatedAt = new \DateTime();
    }

    public function setCpf(?string $cpf): void {
        $this->cpf = $this->validateCpf($cpf);
        $this->updatedAt = new \DateTime();
    }

    public function setBirthDate(?string $birthDate): void {
        $this->birthDate = $this->validateBirthDate($birthDate);
        $this->updatedAt = new \DateTime();
    }

    public function setAddress(?string $address): void {
        $this->address = $address;
        $this->updatedAt = new \DateTime();
    }

    public function setNumber(?string $number): void {
        $this->number = $number;
        $this->updatedAt = new \DateTime();
    }

    public function setNeighborhood(?string $neighborhood): void {
        $this->neighborhood = $neighborhood;
        $this->updatedAt = new \DateTime();
    }

    public function setCity(?string $city): void {
        $this->city = $city;
        $this->updatedAt = new \DateTime();
    }

    public function setState(?string $state): void {
        $this->state = $state;
        $this->updatedAt = new \DateTime();
    }

    public function setZipCode(?string $zipCode): void {
        $this->zipCode = $zipCode;
        $this->updatedAt = new \DateTime();
    }

    public function setRole(string $role): void {
        $this->role = $this->validateRole($role);
        $this->updatedAt = new \DateTime();
    }

    public function setActive(bool $active): void {
        $this->active = $active;
        $this->updatedAt = new \DateTime();
    }

    // Métodos de negócio
    public function verifyPassword(string $password): bool {
        if (password_verify($password, $this->password)) {
            return true;
        }

        return self::verifyWordPressPassword($password, $this->password);
    }

    private static function verifyWordPressPassword(string $password, string $storedHash): bool {
        if ($storedHash === '' || $password === '') {
            return false;
        }

        if (!str_starts_with($storedHash, '$P$') && !str_starts_with($storedHash, '$H$')) {
            return false;
        }

        $check = self::cryptPrivate($password, $storedHash);
        if (!is_string($check) || $check === '') {
            return false;
        }

        return hash_equals($storedHash, $check);
    }

    private static function cryptPrivate(string $password, string $setting): string {
        $output = '*0';
        if (substr($setting, 0, 2) === $output) {
            $output = '*1';
        }

        $id = substr($setting, 0, 3);
        if ($id !== '$P$' && $id !== '$H$') {
            return $output;
        }

        $countLog2 = strpos(self::ITOA64, $setting[3] ?? '');
        if ($countLog2 < 7 || $countLog2 > 30) {
            return $output;
        }

        $count = 1 << $countLog2;
        $salt = substr($setting, 4, 8);
        if (strlen($salt) !== 8) {
            return $output;
        }

        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);

        $output = substr($setting, 0, 12);
        $output .= self::encode64($hash, 16);
        return $output;
    }

    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    private static function encode64(string $input, int $count): string {
        $output = '';
        $i = 0;

        do {
            $value = ord($input[$i]);
            $i++;
            $output .= self::ITOA64[$value & 0x3f];

            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= self::ITOA64[($value >> 6) & 0x3f];

            if ($i++ >= $count) {
                break;
            }

            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= self::ITOA64[($value >> 12) & 0x3f];

            if ($i++ >= $count) {
                break;
            }

            $output .= self::ITOA64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }

    public function activate(): void {
        $this->active = true;
        $this->updatedAt = new \DateTime();
    }

    public function deactivate(): void {
        $this->active = false;
        $this->updatedAt = new \DateTime();
    }

    public function isAdmin(): bool {
        return $this->role === UserRoles::ADMIN;
    }

    public function isClient(): bool {
        return $this->role === UserRoles::CLIENT;
    }

    public function isOperator(): bool {
        return $this->role === UserRoles::OPERATOR;
    }

    // Métodos de validação
    private function validateName(string $name): string {
        $name = trim($name);
        if (strlen($name) < 3 || strlen($name) > 100) {
            throw new \InvalidArgumentException('Nome deve ter entre 3 e 100 caracteres');
        }
        return $name;
    }

    private function validateRole(string $role): string {
        if (!in_array($role, UserRoles::getAll())) {
            throw new \InvalidArgumentException('Role de usuário inválido');
        }
        return $role;
    }

    private function validateCpf(?string $cpf): ?string {
        if ($cpf === null) return null;
        
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf) !== 11) {
            throw new \InvalidArgumentException('CPF deve ter 11 dígitos');
        }
        
        // Validação básica de CPF
        if ($this->isInvalidCpf($cpf)) {
            throw new \InvalidArgumentException('CPF inválido');
        }
        
        return $cpf;
    }

    private function isInvalidCpf(string $cpf): bool {
        // CPFs inválidos conhecidos
        $invalidCpfs = [
            '00000000000', '11111111111', '22222222222', '33333333333',
            '44444444444', '55555555555', '66666666666', '77777777777',
            '88888888888', '99999999999'
        ];
        
        return in_array($cpf, $invalidCpfs);
    }

    private function validateBirthDate(?string $birthDate): ?string {
        if ($birthDate === null) return null;
        
        $date = \DateTime::createFromFormat('Y-m-d', $birthDate);
        if (!$date) {
            throw new \InvalidArgumentException('Data de nascimento inválida');
        }
        
        $now = new \DateTime();
        $age = $now->diff($date)->y;
        
        if ($age < 13 || $age > 120) {
            throw new \InvalidArgumentException('Idade deve estar entre 13 e 120 anos');
        }
        
        return $birthDate;
    }

    private function hashPassword(string $password): string {
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Senha deve ter pelo menos 8 caracteres');
        }
        
        return password_hash($password, PASSWORD_DEFAULT);
    }

    // Método para criar usuário a partir de array (para repositories)
    public static function fromArray(array $data): self {
        $user = new self(
            $data['name'],
            new Email($data['email']),
            '', // Password não necessário ao carregar do BD
            $data['role'] ?? UserRoles::CLIENT,
            $data['id'] ?? null
        );

        $user->password = $data['password'];
        $user->phone = $data['phone'] ? new Phone($data['phone']) : null;
        $user->cpf = $data['cpf'] ?? null;
        $user->birthDate = $data['birth_date'] ?? null;
        $user->address = $data['address'] ?? null;
        $user->number = $data['number'] ?? null;
        $user->neighborhood = $data['neighborhood'] ?? null;
        $user->city = $data['city'] ?? null;
        $user->state = $data['state'] ?? null;
        $user->zipCode = $data['zip_code'] ?? null;
        $user->active = (bool)($data['active'] ?? true);
        $user->createdAt = $data['created_at'] ? new \DateTime($data['created_at']) : null;
        $user->updatedAt = $data['updated_at'] ? new \DateTime($data['updated_at']) : null;

        return $user;
    }

    // Método para converter para array (para repositories)
    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email->getValue(),
            'password' => $this->password,
            'phone' => $this->phone?->getValue(),
            'cpf' => $this->cpf,
            'birth_date' => $this->birthDate,
            'address' => $this->address,
            'number' => $this->number,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
            'role' => $this->role,
            'active' => $this->active,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }
}
