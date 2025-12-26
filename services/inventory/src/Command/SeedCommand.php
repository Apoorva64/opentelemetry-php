<?php

namespace App\Command;

use App\Entity\Stock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed',
    description: 'Seed the database with default stock items',
)]
class SeedCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $stockItems = [
            ['itemId' => 'beef_patty', 'itemName' => 'Beef Patty', 'quantity' => 100],
            ['itemId' => 'veggie_patty', 'itemName' => 'Veggie Patty', 'quantity' => 50],
            ['itemId' => 'lettuce', 'itemName' => 'Lettuce', 'quantity' => 200],
            ['itemId' => 'tomato', 'itemName' => 'Tomato', 'quantity' => 150],
            ['itemId' => 'onion', 'itemName' => 'Onion', 'quantity' => 100],
            ['itemId' => 'bun', 'itemName' => 'Burger Bun', 'quantity' => 200],
            ['itemId' => 'cheddar', 'itemName' => 'Cheddar Cheese', 'quantity' => 100],
            ['itemId' => 'avocado', 'itemName' => 'Avocado', 'quantity' => 30],
            ['itemId' => 'potato', 'itemName' => 'Potato', 'quantity' => 500],
            ['itemId' => 'salt', 'itemName' => 'Salt', 'quantity' => 1000],
            ['itemId' => 'batter', 'itemName' => 'Batter Mix', 'quantity' => 200],
            ['itemId' => 'romaine', 'itemName' => 'Romaine Lettuce', 'quantity' => 100],
            ['itemId' => 'parmesan', 'itemName' => 'Parmesan Cheese', 'quantity' => 50],
            ['itemId' => 'croutons', 'itemName' => 'Croutons', 'quantity' => 200],
            ['itemId' => 'caesar_dressing', 'itemName' => 'Caesar Dressing', 'quantity' => 100],
            ['itemId' => 'coca_cola', 'itemName' => 'Coca Cola', 'quantity' => 500],
            ['itemId' => 'lemon', 'itemName' => 'Lemon', 'quantity' => 200],
            ['itemId' => 'sugar', 'itemName' => 'Sugar', 'quantity' => 1000],
            ['itemId' => 'water', 'itemName' => 'Water', 'quantity' => 1000],
            ['itemId' => 'milk', 'itemName' => 'Milk', 'quantity' => 200],
            ['itemId' => 'chocolate', 'itemName' => 'Chocolate Syrup', 'quantity' => 100],
            ['itemId' => 'ice_cream', 'itemName' => 'Ice Cream', 'quantity' => 100],
            ['itemId' => 'apple', 'itemName' => 'Apple', 'quantity' => 100],
            ['itemId' => 'cinnamon', 'itemName' => 'Cinnamon', 'quantity' => 200],
            ['itemId' => 'pie_crust', 'itemName' => 'Pie Crust', 'quantity' => 50],
        ];

        $created = 0;
        foreach ($stockItems as $data) {
            $existing = $this->em->getRepository(Stock::class)->findOneBy(['itemId' => $data['itemId']]);
            if ($existing) {
                $io->note("Skipping existing: {$data['itemName']}");
                continue;
            }

            $stock = new Stock();
            $stock->setItemId($data['itemId'])
                ->setItemName($data['itemName'])
                ->setQuantity($data['quantity'])
                ->setReservedQuantity(0);

            $this->em->persist($stock);
            $created++;
        }

        $this->em->flush();

        $io->success("Created {$created} stock items.");

        return Command::SUCCESS;
    }
}
