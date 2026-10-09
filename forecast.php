<?php
    # Program: forecast.php
    #    Date: 30-Oct-2025
    #  Author: gerry.walterscheid@gmail.com
    # Descrip: Display daily weather forecast with hourly temp, humidity, rain chance, and wind speed.

    session_start();
    header("Content-type: text/xml");
    header("Refresh:300");

    $urlPath = "http://diskstation/cisco/forecast.php";

    # Recall prior variable state
    $id = $_SESSION['id'] ?? "";            # App name.

    # Prevent data corruption from prior application.
    if ($id == "forecast") {
        $mode = $_SESSION['mode'] + 0;      # Last mode.
        $period = $_SESSION['period'];      # Last period.
        $offset = $_SESSION['offset'];      # Last offset.
        $update = $_SESSION['update']; }    # Time from prior page update.
    else {
        $mode = 0;
        $period = 0;
        $offset = 0;
        $update = 0; }

    # Get SoftKey selection.
    $cmd = $_GET['key'] ?? 0;

    # Ignore old commands generated after page refresh delay.
    if (time() - $update < 300)
		
        # Process user request based on the key pressed.	
    	switch($cmd) {

        case 1 : # prev
            if ($period > 1) 
                $period -= 2;
            break;

        case 2 : # next
            if ($period < 12)
                $period += 2;
            break;

        case 3 : # AM hours
            $offset = 0;
            break;

        case 4 : # PM hours
            if ($offset < 10)
                $offset += 5;
            else
                $offset = 5;
            break;

        case 5 : # mode
            $mode = ($mode + 1)%5;;
            break;
        }

    else
        # Reset the date after the page refresh delay.
		$period = 0;

    switch($mode) {

    case 0 : # daily weather

        # Collect weather data from api.weather.gov if not current.
        $file = '/volume1/web/cisco/forecastDaily.json';

        if ( date('Y-m-d') != date('Y-m-d', filemtime($file)) ) {

            $json_url="https://api.weather.gov/gridpoints/MLB/26,68/forecast";

            $options  = array('http' => array('user_agent' => 'gerry.walterscheid@gmail.com'));
            $context  = stream_context_create($options);

            $json = file_get_contents($json_url, false, $context);
            $data = json_decode($json, TRUE);

            # Write JSON content to local file to minimize API requests.
            $fp = fopen($file, 'w');
            fwrite($fp, $json);
            fclose($fp);
        }

        # Read locally saved weather data to minimize calls to api website.
        $json = file_get_contents($file);
        $data = json_decode($json, TRUE);

        # Un-comment line below to see all weather fields.
        # print_r($data);

        # Skip 1st period if it is at night.
        if ($period == 0 && !$data['properties']['periods'][$period]['isDaytime']) 
            $period = 1;

        echo "<CiscoIPPhoneText>\n";
        echo "  <Title>Weather - Daily Forecast</Title>\n";
        echo "  <Text>";

        if (date('Y-m-d') == substr($data['properties']['generatedAt'],0,10)) 

            for ($i = $period; $i < 14; $i++) {
                $fcast = $data['properties']['periods'][$i]['detailedForecast']; 

                # Print daytime, then overnight forecast.
                if ($data['properties']['periods'][$i]['isDaytime']) 
                    printf("%s ", $fcast); 
                else {
                    printf("Overnight, %s", lcfirst($fcast)); 
                    break; 
                }
            } else {
            print "Unable to access weather data. ";
            print "Please try again later.\n";
        }

        break;

    case 1 : # hourly temp forecast

        # Initialize array to store 1 week of temp data.
        $temp = array(
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ) );

        $weekday = array("Su", "Mo", "Tu", "We", "Th", "Fr", "Sa");

        # Collect weather data from api.weather.gov is not current.
        $file = '/volume1/web/cisco/forecastHourly.json';

        if ( date('Y-m-d') != date('Y-m-d', filemtime($file)) ) {

            $json_url="https://api.weather.gov/gridpoints/MLB/26,68/forecast/hourly";

            $options  = array('http' => array('user_agent' => 'gerry.walterscheid@gmail.com'));
            $context  = stream_context_create($options);

            $json = file_get_contents($json_url, false, $context);
            $data = json_decode($json, TRUE);

            # print_r($data);

            # Write JSON content to local file to minimize API requests.
            $fp = fopen($file, 'w');
            fwrite($fp, $json);
            fclose($fp);
        }

        # Read locally saved weather data to minimize calls to api website.
        $json= file_get_contents($file);
        $data = json_decode($json, TRUE);

        # print_r($data);

        # Load online data to temperature array.
        for ($i = 0; $i < 156; $i++) {
            $day = date('w',strtotime(substr($data['properties']['periods'][$i]['startTime'],0,10)));
            $hr = intval(substr($data['properties']['periods'][$i]['startTime'],11,2));
            $temp[$day][$hr] = $data['properties']['periods'][$i]['temperature'];
        }

        echo "<CiscoIPPhoneText>\n";
        echo "  <Title>Weather - Temperature - F</Title>\n";
        echo "  <Text>";

        # Get the 1st day of temperature data.
        $start = date('w',strtotime(substr($data['properties']['periods'][0]['startTime'],0,10)));

        # Adjust week to start with data for every hour.
        if ($temp[$start][12] == 0)
            $start = (1+$start)%7; 

        # Print table heading.
        printf("Hour   ");
        for ($day = 0; $day < 7; $day++)
            printf("%s   ", $weekday[($day + $start)%7]);

        # printf("\n");

        # Print temperature table.
        for ($hr = 7+$offset; $hr < 12+$offset; $hr++) {
            printf("\n%02d:00  ", $hr);

            for ($day = 0; $day < 7; $day++) {
                $ndx = ($day + $start)%7;

                if ($temp[$ndx][$hr] > 0)
                    printf("%02d   ", $temp[$ndx][$hr]);
                else
                    printf("  -     ");
            }

            # printf("\n");
        }

        break;

    case 2 : # hourly humidity forecast

        # Initialize array to store 1 week of humidity data.
        $humid = array(
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ) );

        $weekday = array("Su", "Mo", "Tu", "We", "Th", "Fr", "Sa");

        # Collect weather data from api.weather.gov is not current.
        $file = '/volume1/web/cisco/forecastHourly.json';

        if ( date('Y-m-d') != date('Y-m-d', filemtime($file)) ) {

            $json_url="https://api.weather.gov/gridpoints/MLB/26,68/forecast/hourly";

            $options  = array('http' => array('user_agent' => 'gerry.walterscheid@gmail.com'));
            $context  = stream_context_create($options);

            $json = file_get_contents($json_url, false, $context);
            $data = json_decode($json, TRUE);

            # print_r($data);

            # Write JSON content to local file to minimize API requests.
            $fp = fopen($file, 'w');
            fwrite($fp, $json);
            fclose($fp);
        }

        # Read locally saved weather data to minimize calls to api website.
        $json= file_get_contents($file);
        $data = json_decode($json, TRUE);

        # print_r($data);

        # Load online data to humidity array.
        for ($i = 0; $i < 156; $i++) {
            $day = date('w',strtotime(substr($data['properties']['periods'][$i]['startTime'],0,10)));
            $hr = intval(substr($data['properties']['periods'][$i]['startTime'],11,2));
			$humid[$day][$hr] = $data['properties']['periods'][$i]['relativeHumidity']['value'];

            # Convert to maintain alignment of columns, where 99 = 100% (fog or rain).
            if ($humid[$day][$hr] == 100)
				$humid[$day][$hr] = 99;
        }

        echo "<CiscoIPPhoneText>\n";
        echo "  <Title>Weather - Humidity - %</Title>\n";
        echo "  <Text>";

        # Get the 1st day of humidity data.
        $start = date('w',strtotime(substr($data['properties']['periods'][0]['startTime'],0,10)));

        # Adjust week to start with data for every hour.
        if ($humid[$start][12] == 0)
            $start = (1+$start)%7; 

        # Print table heading.
        printf("Hour   ");
        for ($day = 0; $day < 7; $day++)
            printf("%s   ", $weekday[($day + $start)%7]);

        # printf("\n");

        # Print humidity table.
        for ($hr = 7+$offset; $hr < 12+$offset; $hr++) {
            printf("\n%02d:00  ", $hr);

            for ($day = 0; $day < 7; $day++) {
                $ndx = ($day + $start)%7;

                if ($humid[$ndx][$hr] > 0)
                    printf("%02d   ", $humid[$ndx][$hr]);
                else
                    printf("  -     ");
            }

            # printf("\n");
        }

        break;

    case 3 : # hourly rain forecast

        # Initialize array to store 1 week of rain data.
        $rain = array(
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ),
            array( 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0 ) );

        $weekday = array("Su", "Mo", "Tu", "We", "Th", "Fr", "Sa");

        # Collect weather data from api.weather.gov is not current.
        $file = '/volume1/web/cisco/forecastHourly.json';

        if ( date('Y-m-d') != date('Y-m-d', filemtime($file)) ) {

            $json_url="https://api.weather.gov/gridpoints/MLB/26,68/forecast/hourly";

            $options  = array('http' => array('user_agent' => 'gerry.walterscheid@gmail.com'));
            $context  = stream_context_create($options);

            $json = file_get_contents($json_url, false, $context);
            $data = json_decode($json, TRUE);

            # print_r($data);

            # Write JSON content to local file to minimize API requests.
            $fp = fopen($file, 'w');
            fwrite($fp, $json);
            fclose($fp);
        }

        # Read locally saved weather data to minimize calls to api website.
        $json= file_get_contents($file);
        $data = json_decode($json, TRUE);

        # print_r($data);

        # Load online data to rain array.
        for ($i = 0; $i < 156; $i++) {
            $day = date('w',strtotime(substr($data['properties']['periods'][$i]['startTime'],0,10)));
            $hr = intval(substr($data['properties']['periods'][$i]['startTime'],11,2));
            $rain[$day][$hr] = $data['properties']['periods'][$i]['probabilityOfPrecipitation']['value'];
        }

        echo "<CiscoIPPhoneText>\n";
        echo "  <Title>Weather - Rain Chance - %</Title>\n";
        echo "  <Text>";

        # Get the 1st day of rain data.
        $start = date('w',strtotime(substr($data['properties']['periods'][0]['startTime'],0,10)));

        # Adjust week to start with data for every hour.
        if ($rain[$start][12] == 0)
            $start = (1+$start)%7; 

        # Print table heading.
        printf("Hour   ");
        for ($day = 0; $day < 7; $day++)
            printf("%s   ", $weekday[($day + $start)%7]);

        # printf("\n");

        # Print rain table.
        for ($hr = 7+$offset; $hr < 12+$offset; $hr++) {
            printf("\n%02d:00  ", $hr);

            for ($day = 0; $day < 7; $day++) {
                $ndx = ($day + $start)%7;

                if ($rain[$ndx][$hr] > 0)
                    printf("%02d   ", $rain[$ndx][$hr]);
                else
                    printf("  -     ");
            }

            # printf("\n");
        }

        break;

    case 4 : # hourly wind speed forecast

        # Initialize array to store 1 week of wind speed data.
        $wind = array(
			array( -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1 ),
			array( -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1 ),
			array( -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1 ),
			array( -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1 ),
			array( -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1 ),
			array( -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1 ),
			array( -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1 ) );

        $weekday = array("Su", "Mo", "Tu", "We", "Th", "Fr", "Sa");

        # Collect weather data from api.weather.gov is not current.
        $file = '/volume1/web/cisco/forecastHourly.json';

        if ( date('Y-m-d') != date('Y-m-d', filemtime($file)) ) {

            $json_url="https://api.weather.gov/gridpoints/MLB/26,68/forecast/hourly";

            $options  = array('http' => array('user_agent' => 'gerry.walterscheid@gmail.com'));
            $context  = stream_context_create($options);

            $json = file_get_contents($json_url, false, $context);
            $data = json_decode($json, TRUE);

            # print_r($data);

            # Write JSON content to local file to minimize API requests.
            $fp = fopen($file, 'w');
            fwrite($fp, $json);
            fclose($fp);
        }

        # Read locally saved weather data to minimize calls to api website.
        $json= file_get_contents($file);
        $data = json_decode($json, TRUE);

        # print_r($data);

        # Load online data to wind speed array.
        for ($i = 0; $i < 156; $i++) {
            $day = date('w',strtotime(substr($data['properties']['periods'][$i]['startTime'],0,10)));
            $hr = intval(substr($data['properties']['periods'][$i]['startTime'],11,2));
            $wind[$day][$hr] = strtok($data['properties']['periods'][$i]['windSpeed']," ");
        }

        echo "<CiscoIPPhoneText>\n";
        echo "  <Title>Weather - Wind Speed - mph</Title>\n";
        echo "  <Text>";

        # Get the 1st day of temperature data.
        $start = date('w',strtotime(substr($data['properties']['periods'][0]['startTime'],0,10)));

        # Adjust week to start with data for every hour.
        if ($wind[$start][12] == -1)
            $start = (1+$start)%7; 

        # Print table heading.
        printf("Hour   ");
        for ($day = 0; $day < 7; $day++)
            printf("%s   ", $weekday[($day + $start)%7]);

        # printf("\n");

        # Print temperature table.
        for ($hr = 7+$offset; $hr < 12+$offset; $hr++) {
            printf("\n%02d:00  ", $hr);

            for ($day = 0; $day < 7; $day++) {
                $ndx = ($day + $start)%7;

                if ($wind[$ndx][$hr] > -1)
                    printf("%02d   ", $wind[$ndx][$hr]);
                else
                    printf("  -     ");
            }

            # printf("\n");
        }

        break;
    }

    switch($mode) {
    case 0 : # daily weather softkey menu

        echo "</Text>\n";

        echo "  <SoftKeyItem>\n";
        echo "    <Name>Mode</Name>\n";
        echo "    <URL>$urlPath?key=5</URL>\n";
        echo "    <Position>1</Position>\n";
        echo "  </SoftKeyItem>\n";

        echo "  <SoftKeyItem>\n";
        echo "    <Name>Prev</Name>\n";
        echo "    <URL>$urlPath?key=1</URL>\n";
        echo "    <Position>2</Position>\n";
        echo "  </SoftKeyItem>\n";

        echo "  <SoftKeyItem>\n";
        echo "    <Name>Next</Name>\n";
        echo "    <URL>$urlPath?key=2</URL>\n";
        echo "    <Position>3</Position>\n";
        echo "  </SoftKeyItem>\n";

        echo "  <SoftKeyItem>\n";
        echo "    <Name>Exit</Name>\n";
        echo "    <URL>SoftKey:Exit</URL>\n";
        echo "    <Position>4</Position>\n";
        echo "  </SoftKeyItem>\n";

        $date = strtotime(substr($data['properties']['periods'][$period]['startTime'],0,10));
        $date = date('D, M jS, Y',$date); 

        echo "  <Prompt>$date</Prompt>\n";
        echo "</CiscoIPPhoneText>";

        break;

    case 1 : # hourly temp forecast softkey menu
    case 2 : # hourly humidity forecast softkey menu
    case 3 : # hourly rain chance forecast softkey menu
    case 4 : # hourly wind speed forecast softkey menu

        echo "</Text>\n";

        echo "  <SoftKeyItem>\n";
        echo "	  <Name>Mode</Name>\n";
        echo " 	  <URL>$urlPath?key=5</URL>\n";
        echo "    <Position>1</Position>\n";
        echo "  </SoftKeyItem>\n";

        echo "  <SoftKeyItem>\n";
        echo "	  <Name>AM</Name>\n";
        echo "    <URL>$urlPath?key=3</URL>\n";
        echo "    <Position>2</Position>\n";
        echo "  </SoftKeyItem>\n";

        echo "  <SoftKeyItem>\n";
        echo "    <Name>PM</Name>\n";
        echo "    <URL>$urlPath?key=4</URL>\n";
        echo "    <Position>3</Position>\n";
        echo "  </SoftKeyItem>\n";

        echo "  <SoftKeyItem>\n";
        echo "    <Name>Exit</Name>\n";
        echo "    <URL>SoftKey:Exit</URL>\n";
        echo "    <Position>4</Position>\n";
        echo "  </SoftKeyItem>\n";

        echo "  <Prompt>Data is updated every day.</Prompt>\n";
        echo "</CiscoIPPhoneText>";

        break;
    }

    # Save current variable state.
    $_SESSION['id'] = "forecast";
    $_SESSION['cmd'] = $cmd;
    $_SESSION['mode'] = $mode;
    $_SESSION['period'] = $period;
    $_SESSION['offset'] = $offset;
    $_SESSION['update'] = time();
?>
