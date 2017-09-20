<?php

    // ------------------- //
    // --- ORM Demo --- //
    // ------------------- //

    // Note: This is just about the simplest database-driven webapp it's possible to create
    // and is designed only for the purpose of demonstrating how ORM works.

    // In case it's not obvious: this is not the correct way to build web applications!

    // Require the ORM file
    require_once("ORM.php");

    // Connect to the demo database file
    ORM::configure('sqlite:./demo.sqlite');

    // This grabs the raw database connection from the ORM
    // class and creates the table if it doesn't already exist.
    // Wouldn't normally be needed if the table is already there.
    $db = ORM::getDb();
    $db->exec(
        "
        CREATE TABLE IF NOT EXISTS contact (
            id INTEGER PRIMARY KEY, 
            name TEXT, 
            email TEXT 
        );"
    );

    // Handle POST submission
    if (!empty($_POST)) {
        // Create a new contact object
        $contact = ORM::table('contact')->create();

        // SHOULD BE MORE ERROR CHECKING HERE!
        // Set the properties of the object
        $contact->name = $_POST['name'];
        $contact->email = $_POST['email'];

        // Save the object to the database
        $contact->save();

        // Redirect to self.
        header('Location: ' . basename(__FILE__));
        exit;
    }

    // Get a list of all contacts from the database
    $count    = ORM::table('contact')->count();
    $contacts = ORM::table('contact')->findMany();
?>

<html>
    <head>
        <title>ORM Demo</title>
    </head>

    <body>
    
        <h1>ORM Demo</h1>

        <h2>Contact List (<?php echo $count; ?> contacts)</h2>
        <ul>
            <?php foreach ($contacts as $contact): ?>
                <li>
                    <strong><?php echo $contact->name ?></strong>
                    <a href="mailto:<?php echo $contact->email; ?>"><?php echo $contact->email; ?></a>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="post" action="">
            <h2>Add Contact</h2>
            <p><label for="name">Name:</label> <input type="text" name="name"></p>
            <p><label for="email">Email:</label> <input type="email" name="email"></p>
            <input type="submit" value="Create" />
        </form>
    </body>
</html>
