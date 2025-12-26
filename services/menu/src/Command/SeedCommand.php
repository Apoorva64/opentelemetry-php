<?php

namespace App\Command;

use App\Entity\MenuItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed',
    description: 'Seed the database with default menu items',
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

        $menuItems = [
            ['name' => 'Classic Burger', 'description' => 'Beef patty with lettuce, tomato, onion', 'price' => '8.50', 'category' => 'burgers', 'ingredients' => ['beef_patty', 'lettuce', 'tomato', 'onion', 'bun']],
            ['name' => 'Cheese Burger', 'description' => 'Classic burger with cheddar cheese', 'price' => '9.50', 'category' => 'burgers', 'ingredients' => ['beef_patty', 'cheddar', 'lettuce', 'tomato', 'bun']],
            ['name' => 'Veggie Burger', 'description' => 'Plant-based patty with fresh vegetables', 'price' => '10.00', 'category' => 'burgers', 'ingredients' => ['veggie_patty', 'lettuce', 'tomato', 'avocado', 'bun']],
            ['name' => 'French Fries', 'description' => 'Crispy golden fries', 'price' => '3.50', 'category' => 'sides', 'ingredients' => ['potato', 'salt']],
            ['name' => 'Onion Rings', 'description' => 'Battered and fried onion rings', 'price' => '4.50', 'category' => 'sides', 'ingredients' => ['onion', 'batter']],
            ['name' => 'Caesar Salad', 'description' => 'Romaine lettuce with caesar dressing', 'price' => '7.00', 'category' => 'salads', 'ingredients' => ['romaine', 'parmesan', 'croutons', 'caesar_dressing']],
            ['name' => 'Coca Cola', 'description' => 'Classic cola drink', 'price' => '2.50', 'category' => 'drinks', 'ingredients' => ['coca_cola']],
            ['name' => 'Lemonade', 'description' => 'Fresh squeezed lemonade', 'price' => '3.00', 'category' => 'drinks', 'ingredients' => ['lemon', 'sugar', 'water']],
            ['name' => 'Chocolate Milkshake', 'description' => 'Thick chocolate shake', 'price' => '5.50', 'category' => 'drinks', 'ingredients' => ['milk', 'chocolate', 'ice_cream']],
            ['name' => 'Apple Pie', 'description' => 'Warm apple pie with cinnamon', 'price' => '4.00', 'category' => 'desserts', 'ingredients' => ['apple', 'cinnamon', 'pie_crust']],
        ];

        $created = 0;
        foreach ($menuItems as $data) {
            $existing = $this->em->getRepository(MenuItem::class)->findOneBy(['name' => $data['name']]);
            if ($existing) {
                $io->note("Skipping existing: {$data['name']}");
                continue;
            }

            $item = new MenuItem();
            $item->setName($data['name'])
                ->setDescription($data['description'])
                ->setPrice($data['price'])
                ->setCategory($data['category'])
                ->setAvailable(true)
                ->setIngredients($data['ingredients']);

            $this->em->persist($item);
            $created++;
        }

        $this->em->flush();

        $io->success("Created {$created} menu items.");

        return Command::SUCCESS;
    }
}
