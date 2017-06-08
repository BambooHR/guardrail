<?php
switch($detail->type) {
	case 'date':   return 1;
	case 'currency':
	case 'dollar': return 2;
	case 'state':
		$countryId = ($specialFields['country']>0 ? cleanCountry($mainDb, $row[ $specialFields['country'] ] ) : $countryId );
		return 3;
		break;
	case 'gender': return 4;
	case 'ssn':    return 5;
	case 'phone':  return 6;
	case 'country':return 7;
	case 'marital_status': return 8;
	case 'status': return 9;
	case 'pay_group': return 10;
	case 'twitter_handle': // default
	case 'contact_url': //contact_url uses the default, but I wanted to specify it in this list.
	case 'employee_access': return 11;
	default:       return $detail->type;
}