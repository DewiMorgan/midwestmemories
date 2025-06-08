<?php

declare(strict_types=1);

namespace MidwestMemories\Enum;
require_once('HttpMethod.php');
require_once('EndpointPath.php');

/**
 * Enum representing all API endpoints in the system.
 */
enum EndpointKey: string
{
    case DELETE_COMMENT = HttpMethod::DELETE->value . '#' . EndpointPath::COMMENT->value;
    case GET_COMMENT = HttpMethod::GET->value . '#' . EndpointPath::COMMENT->value;
    case GET_CURSOR = HttpMethod::GET->value . '#' . EndpointPath::CURSOR->value;
    case GET_DOWNLOAD = HttpMethod::GET->value . '#' . EndpointPath::DOWNLOAD->value;
    case GET_PROCESS = HttpMethod::GET->value . '#' . EndpointPath::PROCESS->value;
    case GET_USER = HttpMethod::GET->value . '#' . EndpointPath::USER->value;
    case POST_COMMENT = HttpMethod::POST->value . '#' . EndpointPath::COMMENT->value;
    case POST_CURSOR = HttpMethod::POST->value . '#' . EndpointPath::CURSOR->value;
    case POST_DOWNLOAD = HttpMethod::POST->value . '#' . EndpointPath::DOWNLOAD->value;
    case POST_PROCESS = HttpMethod::POST->value . '#' . EndpointPath::PROCESS->value;
    case POST_USER = HttpMethod::PUT->value . '#' . EndpointPath::USER->value;
    case PUT_COMMENT = HttpMethod::PUT->value . '#' . EndpointPath::COMMENT->value;
    case PUT_USER = HttpMethod::POST->value . '#' . EndpointPath::USER->value;
}
