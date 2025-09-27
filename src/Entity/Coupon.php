<?php
declare(strict_types=1);

namespace App\Entity;

use App\Enum\Coupon\Status;
use App\Enum\Coupon\Type;
use App\Repository\CouponRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CouponRepository::class)]
#[ORM\Table(name: 'coupons')]
#[ORM\HasLifecycleCallbacks]
class Coupon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    private string $code;

    #[ORM\Column(type: Types::ENUM, length: 20, options: ['default' => Type::FIXED])]
    //#[Assert\Choice(choices: Type::values())]
    private Type $type = Type::FIXED;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: false, options: ['default' => 0])]
    #[Assert\Positive]
    private float $value = 0;

    #[ORM\Column(type: Types::ENUM, length: 20, options: ['default' => Status::ACTIVE])]
    //#[Assert\Choice(choices: Status::values())]
    private ?Status $status = Status::ACTIVE;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $usageLimit = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $timesUsed = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'coupons', cascade: ['persist', 'detach'])]
    private Collection $products;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);
        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(Type $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getUsageLimit(): ?int
    {
        return $this->usageLimit;
    }

    public function setUsageLimit(?int $usageLimit): static
    {
        $this->usageLimit = $usageLimit;
        return $this;
    }

    public function getTimesUsed(): ?int
    {
        return $this->timesUsed;
    }

    public function setTimesUsed(int $timesUsed): static
    {
        $this->timesUsed = $timesUsed;
        return $this;
    }

    public function incrementTimesUsed(): static
    {
        $this->timesUsed++;
        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeImmutable $validFrom): static
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeImmutable $validTo): static
    {
        $this->validTo = $validTo;
        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        $this->products->removeElement($product);
        return $this;
    }

    public function calculateDiscount(float $totalAmount, array $products = []): float
    {
        if (!$this->isValid()) {
            return 0.0;
        }

        $discount = match ($this->type) {
            Type::FIXED => min((float) $this->value, $totalAmount),
            Type::PERCENTAGE => $totalAmount * ((float) $this->value / 100),
            default => 0.0,
        };

        return round($discount, 2);
    }

    public function isValid(): bool
    {
        $now = new \DateTimeImmutable();

        if ($this->status !== Status::ACTIVE) {
            return false;
        }

        if ($this->usageLimit !== null && $this->timesUsed >= $this->usageLimit) {
            return false;
        }

        if ($this->validFrom !== null && $now < $this->validFrom) {
            return false;
        }

        if ($this->validTo !== null && $now > $this->validTo) {
            return false;
        }

        return true;
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isFixed(): bool
    {
        return $this->type === Type::FIXED;
    }

    public function isPercentage(): bool
    {
        return $this->type === Type::PERCENTAGE;
    }
}