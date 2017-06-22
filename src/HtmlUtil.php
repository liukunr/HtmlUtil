<?php
class HtmlUtil{

    /**
     * 生成html摘要
     *
     * @param string $html
     * @param int $max
     * @param string $suffix
     * @param integer $img
     * @return string
     */
    public static function abstract($html, $max, $suffix = '',$img = -1)
    {
        $non_paired_tags = array('br', 'hr', 'img', 'input', 'param'); // 非成对标签
        $html = trim($html);
        $count = 0; // 有效字符计数(一个HTML实体字符算一个有效字符)
        $tag_status = 0; // (0:非标签, 1:标签开始, 2:标签名开始, 3:标签名结束)
        $nodes = array(); // 存放解析出的节点(文本节点:array(0, '文本内容', 'text', 0), 标签节点:array(1, 'tag', 'tag_name', '标签性质:0:非成对标签,1:成对标签的开始标签,2:闭合标签'))
        $segment = ''; // 文本片段
        $tag_name = ''; // 标签名
        for ($i = 0; $i < strlen($html); $i++) {
            $char = $html[$i]; // 当前字符

            $segment .= $char; // 保存文本片段

            if ($tag_status == 4) {
                $tag_status = 0;
            }

            if ($tag_status == 0 && $char == '<') {
                // 没有开启标签状态,设置标签开启状态
                $tag_status = 1;
            }

            if ($tag_status == 1 && $char != '<') {
                // 标签状态设置为开启后,用下一个字符来确定是一个标签的开始
                $tag_status = 2; //标签名开始
                $tag_name = ''; // 清空标签名

                // 确认标签开启,将标签之前保存的字符版本存为文本节点
                $nodes[] = array(0, substr($segment, 0, strlen($segment) - 2), 'text', 0);
                $segment = '<' . $char; // 重置片段,以标签开头
            }

            if ($tag_status == 2) {
                // 提取标签名
                if ($char == ' ' || $char == '>' || $char == "\t") {
                    $tag_status = 3; // 标签名结束
                } else {
                    $tag_name .= $char; // 增加标签名字符
                }
            }

            if ($tag_status == 3 && $char == '>') {
                $tag_status = 4; // 重置标签状态
                $tag_name = strtolower($tag_name);

                // 跳过成对标签的闭合标签
                $tag_type = 1;
                if (in_array($tag_name, $non_paired_tags)) {
                    // 非成对标签
                    $tag_type = 0;
                } elseif ($tag_name[0] == '/') {
                    $tag_type = 2;
                }

                // 标签结束,保存标签节点
                $nodes[] = array(1, $segment, $tag_name, $tag_type);
                $segment = ''; // 清空片段
            }

            if ($tag_status == 0) {
                if ($char == '&') {
                    // 处理HTML实体,10个字符以内碰到';',则认为是一个HTML实体
                    for ($e = 1; $e <= 10; $e++) {
                        if ($html[$i + $e] == ';') {
                            $segment .= substr($html, $i + 1, $e); // 保存实体
                            $i += $e; // 跳过实体字符所占长度
                            break;
                        }
                    }
                } else {
                    // 非标签情况下检查有效文本
                    $char_code = ord($char); // 字符编码
                    if ($char_code >= 224) // 三字节字符
                    {
                        $segment .= $html[$i + 1] . $html[$i + 2]; // 保存字符
                        $i += 2; // 跳过下2个字符的长度
                    } elseif ($char_code >= 129) // 双字节字符
                    {
                        $segment .= $html[$i + 1];
                        $i += 1; // 跳过下一个字符的长度
                    }
                }

                $count++;
                if ($count == $max) {
                    $nodes[] = array(0, $segment . $suffix, 'text', 0);
                    break;
                }
            }
        }

        $html = '';
        $tag_open_stack = array(); // 成对标签的开始标签栈
        for ($i = 0; $i < count($nodes); $i++) {
            $node = $nodes[$i];
            if ($node[3] == 1) {
                array_push($tag_open_stack, $node[2]); // 开始标签入栈
            } elseif ($node[3] == 2) {
                array_pop($tag_open_stack); // 碰到一个结束标签,出栈一个开始标签
            }
            $html .= $node[1];
        }

        while ($tag_name = array_pop($tag_open_stack)) // 用剩下的未出栈的开始标签补齐未闭合的成对标签
        {
            $html .= '</' . $tag_name . '>';
        }

        //对img标签限制个数
        if($img != -1){
            $html=preg_replace_callback("/<\s*img\s+[^>]*?src\s*=\s*(\'|\")(.*?)\\1[^>]*?\/?\s*>/i", function($r) use($img) {
            static $n = 0;
            return $n++ < $img ? $r[0] : '';
            }, $html);
        }

        return $html;
    }
}