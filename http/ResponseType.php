<?php
/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);

namespace nova\framework\http;

/**
 * HTTP响应类型的枚举
 */
enum ResponseType {
    /** JSON格式响应 */
    case JSON;
    
    /** HTML格式响应 */
    case HTML;
    
    /** XML格式响应 */
    case XML;
    
    /** 纯文本格式响应 */
    case TEXT;
    
    /** 文件下载响应 */
    case FILE;
    
    /** 静态资源响应 */
    case STATIC;
    
    /** Server-Sent Events响应 */
    case SSE;
    
    /** 重定向响应 */
    case REDIRECT;
    
    /** 无响应内容 */
    case NONE;
    
    /** 原始数据响应 */
    case RAW;
}