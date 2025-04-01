<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Email Template</title>

    <style>

        body {

            font-family: Arial, sans-serif;

            margin: 0;

            padding: 0;

            background-color: #ffffff; /* White background color */

        }

        .container {

            max-width: 600px;

            margin: 0 auto;

            padding: 20px;

            border: 1px solid #dddddd; /* Border color */



        }

        .header {

            background-color:#ffffff;

            color: #000;

            padding: 10px;

            border-top-left-radius: 10px;

            border-top-right-radius: 10px;

            font-weight: 600;

        }

        .body {

            padding: 20px;

            background-color: white;

        }

        .footer {

            background-color: #4CAF50;

            color: white;

            text-align: center;

            padding: 10px;

            border-bottom-left-radius: 10px;

            border-bottom-right-radius: 10px;

        }

    </style>

</head>

<body>

    <div class="container">

        <div class="header">

        <h2>Aayurphysio Clinic</h2>

        </div>

        <div class="body">

            <p>Hi <?php echo $full_name; ?>,</p>

            <p>New Appointment Booked</p>

          
            <p>We're looking forward to seeing you on

                <?=$date;?>, at <?=$slottime;?>.

            </p>

            <p>Here's what you booked</p>


                <p><b>Date :</b> <?=$date;?>, </p>

                <p><b>Time : </b> <?=$slottime;?> </p>



        </div>

     

    </div>

</body>

</html>

