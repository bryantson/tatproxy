<?php

/**
 * The high-level code for the checkRegistrationCode API call.
 * See index.php for usage details.
 * 
 * Responds with whether the provided registration code is valid. The registration code is defined in `config.json`.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// process the GET parameters
if ( !isset($_GET['code']) ) {
    errorExit( 400, 'You must define the "code" GET parameter.' );
}

$code = $_GET['code'];

addToLog( 'command: checkRegistrationCode. GET params received:', $_GET );

// get special registration codes, which aren't in salesforce
logSection( 'Getting special registration codes' );
$regCodes = getSpecialRegistrationCodes();
// check if one of the codes matches, and return info
if ( $code === $regCodes['individual-volunteer-distributors'] ) {
    echo json_encode( (object)array(
        'success' => TRUE,
        'volunteerType' => 'volunteerDistributor',
        'isIndividualDistributor' => true
    ));
    exit;
} else if ( $code === $regCodes['tat-ambassadors'] ) {
    echo json_encode( (object)array(
        'success' => TRUE,
        'volunteerType' => 'ambassadorVolunteer',
    ));
    exit;
}


// check the submitted code against registration codes saved in sf
logSection( 'Checking registration code in salesforce' );
makeSalesforceRequestWithTokenExpirationCheck( function() use ($code) {
    $escapedCode = escapeSingleQuotes( $code );
    return getAllSalesforceQueryRecordsAsync( "SELECT Id from Account WHERE TAT_App_Registration_Code__c = '{$escapedCode}'" );
})->then( function($records) {
    if ( sizeof($records) === 0 ) {
        $message = json_encode(array(
            'errorCode' => 'INCORRECT_REGISTRATION_CODE',
            'message' => 'The registration code was incorrect.'
        ));
        throw new ExpectedException( $message );
    } else {
        // find the team coordinators associated with this Account
        logSection( 'Finding team coordinators associated with this account' );
        $accountId = $records[0]->Id;
        return getTeamCoordinators( $accountId )->then( function($coordinators) use($accountId) {
            echo json_encode( (object)array(
                'success' => TRUE,
                'accountId' => $accountId,
                'volunteerType' => 'volunteerDistributor',
                'isIndividualDistributor' => false,
                'teamCoordinators' => $coordinators
            ));
        });
    }
})->otherwise(
    $handleRequestFailure
);

$loop->run();
