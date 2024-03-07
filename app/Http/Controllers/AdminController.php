<?php

namespace App\Http\Controllers;

// Models
use App\Models\Order;
use App\Models\OrderItem;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public $formattedPartList;

    public function index()
    {
        // A simple page to upload order data to the database

        return view('admin.index');
    }

    public function upload(Request $request)
    {
        try {
            // Handle CSV upload
            $request->validate([
                'csv_file' => 'required|file|mimes:csv,txt'
            ]);

            $path = $request->file('csv_file')->store('uploads');

            $file = Storage::get($path);
            $rows = array_map('str_getcsv', explode("\n", $file));
            array_shift($rows);
            array_pop($rows);

            $formattedOrders = $this->formatOrders($rows);

            // API Call
            $response = Http::get('https://nt5gkznl19.execute-api.eu-west-1.amazonaws.com/prod/products?$skip=0&$top=100');
            $parts = $response->json()['value'];
            $this->formattedPartList = $this->formatParts($parts);
            dump($this->formattedPartList);

            $formattedOrders = $this->getWeightsForOrders($formattedOrders);

            $formattedOrders = $this->generateNames($formattedOrders);

            dump($formattedOrders);

            $this->saveOrders($formattedOrders);

            // Return
            return redirect('/orders');
        } catch (\Throwable $th) {
            return back()->withInput()->withErrors(['error' => 'Error uploading orders CSV: ' . $th->getMessage()]);
        }
    }

    private function saveOrders($orders)
    {
        foreach ($orders as $id => $order) {
            if ($this->saveOrder($order)) {
                foreach ($order["Parts"] as $sku => $quantity) {
                    $this->saveOrderItem($order, $sku, $quantity);
                }
            }            
        }
    }

    private function saveOrder($orderData) 
    {
        try {
            $order = new Order();

            $order->id = $orderData['Order Id'];
            $order->customer_name = $orderData['Customer Name'];
            $order->robot_name = $orderData['Robot Name'];
            $order->weight = $orderData['Total Weight'];
            $order->most_used_category = $orderData["Most Used Category"];

            $order->save();
            return true;
        } catch (\Throwable $th) {
            Log::error('An error occurred while saving an order item: ' . $th->getMessage());
            return false;
        }
    }

    private function saveOrderItem($orderData, $orderItemSku, $orderItemQuantity) 
    {
        try {
            $part = $this->formattedPartList[$orderItemSku];

            $orderItem = new OrderItem();

            $orderItem->order_id = $orderData['Order Id'];
            $orderItem->sku = $orderItemSku;
            $orderItem->description = $part['product_name'];
            $orderItem->category = $part['category'];
            $orderItem->weight = $part['weight'];
            $orderItem->quantity = $orderItemQuantity;

            $orderItem->save();
            return true;
        } catch (\Throwable $th) {
               Log::error('An error occurred while saving an order item: ' . $th->getMessage());
               return false;
        }
    }

    private function generateNames($formattedOrders)
    {
        foreach ($formattedOrders as $key => $order) {
            $mostUsedCategory = $this->getMostUsedCategory($order, $this->formattedPartList);
            $formattedOrders[$key]["Most Used Category"] = $mostUsedCategory;

            $name = $this->generateName($order, $mostUsedCategory);

            $formattedOrders[$key]["Robot Name"] = $name;
        }

        return $formattedOrders;
    }

    private function generateName($order, $mostUsedCategory)
    {
        // Get a part which has this category
        $partName = $this->pickPartWithCategory($order, $mostUsedCategory);;

        // Split the name
        $splitPartName = explode(" ", $partName);

        // Choose a random part of the name to focus on
        $name = $splitPartName[rand(0, count($splitPartName) - 1)];
    
        // If the name is long, cut it in half
        $nameLengthLimit = 6;
        if (strlen($name) > $nameLengthLimit) {
            $allowedLength = ceil(strlen($name) / 2);
            $name = substr($name, 0, $allowedLength);
        }

        // Choose prefix, suffix or both
        $extrasType = rand(0, 4);
        switch ($extrasType) {
            case 0:
            case 1:
                // Prefix
                $name = $this->getPrefix() . " " . $name;
                break;
            case 2:
            case 3:
                // Suffix
                $name .= " " . $this->getSuffix();
                break;
            case 4:
                // Both
                $name = $this->getPrefix() . " " . $name . " " . $this->getSuffix();
                break;
            default:
                
                break;
        }

        return $name;
    }

    private function pickPartWithCategory($order, $category)
    {
        $partList = [];
        foreach ($order["Parts"] as $sku => $quantity) {
            if ($this->formattedPartList[$sku]["category"] == $category)
            {
                $partList[] = $this->formattedPartList[$sku]["product_name"];
            }
        }

        $randomIndex = rand(0, count($partList) - 1);
        return $partList[$randomIndex];
    }

    private function getMostUsedCategory($order)
    {
        $categories = [];
        foreach ($order["Parts"] as $sku => $quantity) {
            // Get the category first
            $category = $this->formattedPartList[$sku]["category"];

            // Check if there is already a key
            if (!$this->keyExists($category, $categories)) {
                $categories[$category] = 0;
            }

            // Add to the category tally
            $categories[$category] += $quantity;
        }

        // Find the most used category
        $highestValue = max($categories);
        return array_search($highestValue, $categories);
    }

    private function getWeightsForOrders($formattedOrders) 
    {
        foreach ($formattedOrders as $key => $order) {
            $formattedOrders[$order["Order Id"]]["Total Weight"] = $this->getTotalWeight($order, $this->formattedPartList);
        }

        return $formattedOrders;
    }

    private function getTotalWeight($order)
    {
        $totalWeight = 0;
        
        foreach ($order["Parts"] as $sku => $quantity) {
            $totalWeight += $this->getPartWeight($sku, $quantity, $this->formattedPartList);
        }

        return $totalWeight;
    }

    private function getPartWeight($sku, $quantity)
    {
        if (!$this->keyExists($sku, $this->formattedPartList)) {
            // Error
            return -1;
        }

        return $this->formattedPartList[$sku]["weight"] * $quantity;
    }

    private function formatOrders($orders) 
    {
        // $orders[$i][0] = id
        // $orders[$i][1] = customer name
        // $orders[$i][2] = part sku
        // $orders[$i][3] = quantity of the part

        $formattedOrders = [];

        for ($i=0; $i < count($orders); $i++) 
        { 
            $keyId = $orders[$i][0];
            $order = $orders[$i];
            if (!$this->keyExists($keyId, $formattedOrders)) {
                // The key doesn't exist, create it
                $formattedOrders[$keyId] = [];
                $formattedOrders[$keyId]["Order Id"] = $keyId;
                $formattedOrders[$keyId]["Customer Name"] = $order[1];
                $formattedOrders[$keyId]["Parts"] = [];
            }

            $sku = $order[2];
            if (!$this->keyExists($sku, $formattedOrders[$keyId]["Parts"])) {
                // The key doesn't exist, create it
                $formattedOrders[$keyId]["Parts"][$sku] = 0;
            }

            $formattedOrders[$keyId]["Parts"][$sku] += $order[3]; // Add the quantity
        }

        return $formattedOrders;
    }

    private function keyExists($key, $array) {
        return array_key_exists($key, $array);
    }

    private function formatParts($parts) 
    {
        $formattedPartList = [];
        for ($i=0; $i < count($parts); $i++) 
        { 
            $part = $parts[$i];
            $formattedPartList[$part['sku']] = $part;
        }

        return $formattedPartList;
    }





    private function getPrefix()
    {
        $prefix = [
            'Silly',
            'Old',
            'Young',
            'Balding',
            'Short',
            'Smarmy',
            'Speedy',
            'Oily',
            'Clumsy',
            'Gigantic',
            'Tiny',
            'Grumpy',
            'Lazy',
            'Crazy',
            'Fluffy',
            'Sneaky',
            'Wacky',
            'Gassy',
            'Cheeky',
            'Funky',
            'Noisy',
            'Slimy',
            'Zippy',
            'Screwy',
            'Quirky',
            'Chunky',
            'Fuzzy',
            'Grouchy',
            'Squishy',
            'Droopy',
            'Giggly',
            'Bubbly',
            'Squeaky',
            'Snappy',
            'Snoozy',
            'Loopy',
            'Itchy',
            'Bumpy',
            'Chubby',
            'Dizzy',
            'Saucy',
            'Wrinkly',
            'Sappy',
            'Spooky',
            'Dorky',
            'Pudgy',
            'Peculiar',
            'Wonky',
            'Nerdy',
            'Dopey',
            'Goofy',
            'Grimy',
            'Nippy',
            'Stubby',
            'Sloppy',
            'Sassy',
            'Lanky',
            'Stinky',
            'Snazzy',
            'Dopey',
            'Slick',
            'Weird',
            'Fancy',
            'Clueless',
            'Kooky',
            'Squeaky',
            'Groovy',
            'Cranky',
            'Frumpy',
            'Muddy',
            'Bizarre',
            'Wobbly',
            'Sappy',
            'Fidgety',
            'Spiffy',
            'Wonky',
            'Whacky',
            'Droopy',
            'Snappy',
            'Crabby',
            'Wimpy',
            'Nutty',
            'Glitchy',
            'Rusty',
            'Creaky',
            'Wonky',
            'Clumsy',
            'Zany',
            'Peculiar',
            'Wonky',
            'Nerdy',
            'Dopey',
            'Goofy',
            'Grimy',
            'Nippy',
            'Stubby',
            'Sloppy',
            'Sassy',
            'Lanky',
            'Stinky',
            'Snazzy',
            'Dopey',
            'Slick',
            'Weird',
            'Fancy',
            'Clueless',
            'Kooky',
            'Squeaky',
            'Groovy',
            'Cranky',
            'Frumpy',
            'Muddy',
            'Bizarre',
            'Wobbly',
            'Sappy',
            'Fidgety',
            'Spiffy',
            'Wonky',
            'Whacky',
            'Droopy',
            'Snappy',
            'Crabby',
            'Wimpy',
            'Nutty',
            'Glitchy',
            'Rusty',
            'Creaky',
            'Wonky',
            'Clumsy',
            'Zany',
            'Peculiar',
            'Wonky',
            'Nerdy',
            'Dopey',
            'Goofy',
            'Grimy',
            'Nippy',
            'Stubby',
            'Sloppy',
            'Sassy',
            'Lanky',
            'Stinky',
            'Snazzy',
            'Dopey',
            'Slick',
            'Weird',
            'Fancy',
            'Clueless',
            'Kooky',
            'Squeaky',
            'Groovy',
            'Cranky',
            'Frumpy',
            'Muddy',
            'Bizarre',
            'Wobbly',
            'Sappy',
            'Fidgety',
            'Spiffy',
            'Wonky',
            'Whacky',
            'Droopy',
            'Snappy',
            'Crabby',
            'Wimpy',
            'Nutty',
            'Glitchy',
            'Rusty',
            'Creaky',
            'Wonky',
            'Clumsy',
            'Zany',
            'Peculiar',
            'Wonky',
            'Nerdy',
            'Dopey',
            'Goofy',
            'Grimy',
            'Nippy',
            'Stubby',
            'Sloppy',
            'Sassy',
            'Lanky',
            'Stinky',
            'Snazzy',
            'Dopey',
            'Slick',
            'Weird',
            'Fancy',
            'Clueless',
            'Kooky',
            'Squeaky',
            'Groovy',
            'Cranky',
            'Frumpy',
            'Muddy',
            'Bizarre',
            'Wobbly',
            'Sappy',
            'Fidgety',
            'Spiffy',
            'Wonky',
            'Whacky',
            'Droopy',
            'Snappy',
            'Crabby',
            'Wimpy',
            'Nutty',
            'Glitchy',
            'Rusty',
            'Creaky',
            'Wonky',
            'Clumsy',
            'Zany',
            'Peculiar',
            'Wonky',
            'Nerdy',
            'Dopey',
            'Goofy',
            'Grimy',
            'Nippy',
            'Stubby',
            'Sloppy',
            'Sassy',
            'Lanky',
            'Stinky',
            'Snazzy',
            'Dopey',
            'Slick',
            'Weird',
            'Fancy',
            'Clueless',
            'Kooky',
            'Squeaky',
            'Groovy',
            'Cranky',
            'Frumpy',
            'Muddy',
            'Bizarre',
            'Wobbly',
            'Sappy',
            'Fidgety',
            'Spiffy',
            'Wonky',
            'Whacky',
            'Droopy',
            'Snappy',
            'Crabby',
            'Wimpy',
        ];

        $randomIndex = rand(0, count($prefix) - 1);
        return $prefix[$randomIndex];
    }

    private function getSuffix()
    {
        $suffix = [
            'Bot',
            'Cyborg',
            'Droid',
            'Mech',
            'Gizmo',
            'Widget',
            'Robo',
            'Mecha',
            'Cyber',
            'Automaton',
            'Contraption',
            'Contrivance',
            'Automaton',
            'Automobile',
            'Contraption',
            'Device',
            'Implement',
            'Machine',
            'Mechanism',
            'Appliance',
            'Contrivance',
            'Gadget',
            'Gear',
            'Rig',
            'Tool',
            'Apparatus',
            'Contraption',
            'Implement',
            'Instrument',
            'Mechanism',
            'Contraption',
            'Gadget',
            'Gizmo',
            'Widget',
            'Contrivance',
            'Device',
            'Gadget',
            'Gear',
            'Gizmo',
            'Mechanism',
            'Robot',
            'Automaton',
            'Bot',
            'Cyborg',
            'Droid',
            'Robo',
            'Mecha',
            'Cyber',
            'Automaton',
            'Mech',
            'Gizmo',
        ];

        $randomIndex = rand(0, count($suffix) - 1);
        return $suffix[$randomIndex];
    }

}
