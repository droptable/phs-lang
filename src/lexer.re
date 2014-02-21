/
  # ignore whitespace at the beginning
  ^[\h\v]*
  
  # stuff we actually care about
  (
    # comments
    (?:
      # single line
      (?:[\/]{2}|[#]).*(?=\v|$)
      
      # multi line
      | \/\*(?:[^*]+|\*[^\/])*\*\/
    )
  | 
    # numbers with base
    (?:      
      # hex
      0[Xx][a-fA-F0-9]+
      
      # bin
      | 0[Bb][01]+
      
      # oct
      | 0[0-7]+
    )
  |
    # numbers
    (?:      
      # float
      (?:
        (?:
          # variant 1
          \d+\.\d*
          
          # variant 2
          | \d*\.\d+
        )

        # exponent stuff can be added here
      )
      
      |
      
      # integer
      (?:
        \d+ 
        
        # optional suffix
        (?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)?
      )
    )
  | 
    # strings
    (?:
      # single quote
      (?:
        [rc]?[']
        (?:
          # eat everything except '\\' and '\''
          [^\\']+ 
          
          # allow '\\' followed by a single char
          | [\\].
        )*
        [']
      )
      
      # double quote
      | (?:
        [rc]?["]
        (?:
          # eat everything except '\\' and '"'
          [^\\"]+ 
          
          # allow '\\' followed by a single char
          | [\\].
        )*
        ["]
      )
    )
  |
    # the "is" and "is not" operator
    (?:is!|(?:!is|is(?:no?t)?)(?=\s|$))
  |
    # words (identifiers)
    [a-zA-Z_\x7f-\xff\$][a-zA-Z0-9_\x7f-\xff\$]*
    
    # allow '?' and '!' as suffix on function-names or calls
    # (?:[?!]\s*(?=[\(]))?
  | 
    # operators and punctuation
    (?:    
      # basic punctuation
      [;,{}\(\)\[\]\$@\#]
      
      # combinations
      | (?:[=]>|=~|!\.|:>|<:)
      
      # double or single with '=' at the end
      | (?:[|]{1,2}|[&]{1,2}|[*]{1,2})=
      
      # wrong tokens with error recovery
      | [!=]==
      
      # single with an optional '=' at the end
      | [\/~%!<>]=?
      
      # single with an '=' at the end
      | [+\-\^]=
      
      # double or single
      | (?:
          [|]{1,2}
        | [&]{1,2}
        | [\^]{1,2}
        | [*]{1,2}
        | [:]{1,2}
        | [<]{1,2}
        | [>]{1,2}
        | [+]{1,2}
        | [\-]{1,2}
        | [=]{1,2}
      )

      # tripple, double or single
      | [.]{1,3}
      
      # single
      | [?:]
    )
  )
/xS
