<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AskIaSessionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * §25 : persiste l'échange question/réponse pour (a) permettre de
 * reprendre une session si l'app mobile est fermée en cours de route, (b)
 * garder un historique auditable de ce que l'IA a répondu — utile si un
 * jour une réponse posait question (santé = domaine sensible, traçabilité
 * mérite d'être là dès le premier lot plutôt qu'ajoutée après coup).
 */
enum AskIaSessionStatus: string
{
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
}

#[ORM\Entity(repositoryClass: AskIaSessionRepository::class)]
#[ORM\Table(name: 'ask_ia_session')]
class AskIaSession
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Medicine::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Medicine $medicine;

    #[ORM\Column(type: 'string', enumType: AskIaSessionStatus::class)]
    private AskIaSessionStatus $status = AskIaSessionStatus::IN_PROGRESS;

    /**
     * @var array<int, array{question: string, answer: string}>
     */
    #[ORM\Column(type: 'json')]
    private array $qaHistory = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $conclusionText = null;

    /** Question actuellement posée, en attente de réponse OUI/NON — distincte de qaHistory qui ne contient que les paires déjà répondues. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $currentQuestion = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, Medicine $medicine)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->medicine = $medicine;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getMedicine(): Medicine
    {
        return $this->medicine;
    }

    public function getStatus(): AskIaSessionStatus
    {
        return $this->status;
    }

    /** @return array<int, array{question: string, answer: string}> */
    public function getQaHistory(): array
    {
        return $this->qaHistory;
    }

    public function recordAnswer(string $question, string $answer): static
    {
        if ($this->status !== AskIaSessionStatus::IN_PROGRESS) {
            throw new \DomainException('Cette session est déjà terminée.');
        }

        $this->qaHistory[] = ['question' => $question, 'answer' => $answer];
        $this->currentQuestion = null;

        return $this;
    }

    public function setCurrentQuestion(string $question): static
    {
        $this->currentQuestion = $question;

        return $this;
    }

    public function getCurrentQuestion(): ?string
    {
        return $this->currentQuestion;
    }

    public function complete(string $conclusionText): static
    {
        $this->status = AskIaSessionStatus::COMPLETED;
        $this->conclusionText = $conclusionText;
        $this->currentQuestion = null;

        return $this;
    }

    public function getConclusionText(): ?string
    {
        return $this->conclusionText;
    }
}
