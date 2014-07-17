%pure_parser

/* this grammar does not produce a 100% valid syntax-tree.
   but that's okay, the tree gets validated in a later step */

/* 
  shift/reduce conflicts:
   - 1x if-elsif resolved by shift
   - 1x elsif-elsif resolved by shift
   - 1x if-else resolved by shift
*/ 
%expect 3

%left ','
%right T_ARR
%right T_YIELD
%right T_APLUS T_AMINUS T_AMUL T_ADIV T_AMOD T_APOW
       T_ACONCAT 
       T_ABIT_OR T_ABIT_AND T_ABIT_XOR
       T_ABOOL_OR T_ABOOL_AND T_ABOOL_XOR
       T_ASHIFT_L T_ASHIFT_R 
       T_AREF '='
%left T_RANGE
%right '?' ':'
%left T_BOOL_OR
%left T_BOOL_XOR
%left T_BOOL_AND
%left '|'
%left '^'
%left '&'
%left T_EQ T_NEQ
%nonassoc T_IN T_NIN T_IS T_NIS T_GTE T_LTE '>' '<'
%left T_SL T_SR
%left '+' '-' '~'
%left '*' '/' '%'
%right T_POW
%right T_AS 
%right T_REST
%right T_DEL
%right T_INC T_DEC
%right '!' 
%right T_NEW
%left '.' '[' ']'
%nonassoc '(' ')'
%nonassoc T_DDDOT

%token T_FN
%token T_LET
%token T_USE
%token T_ENUM
%token T_CLASS
%token T_TRAIT
%token T_IFACE
%token T_MODULE
%token T_REQUIRE

%token T_IDENT
%token T_LNUM
%token T_DNUM
%token T_SNUM
%token T_STRING
%token T_REGEXP
%token T_TRUE
%token T_FALSE
%token T_NULL
%token T_THIS
%token T_SUPER

%token T_GET
%token T_SET

%token T_DO
%token T_IF
%token T_ELSIF
%token T_ELSE
%token T_FOR
%token T_TRY
%token T_GOTO
%token T_BREAK
%token T_CONTINUE
%token T_PRINT
%token T_THROW
%token T_CATCH
%token T_FINALLY
%token T_WHILE
%token T_ASSERT
%token T_SWITCH
%token T_CASE
%token T_DEFAULT
%token T_RETURN

%token T_CONST
%token T_FINAL
%token T_GLOBAL
%token T_STATIC
%token T_EXTERN
%token T_PUBLIC
%token T_PRIVATE
%token T_PROTECTED

%token T_SEALED
%token T_INLINE

%token T_ALIAS

%token T_PHP
%token T_TEST

%token T_CDIR
%token T_CFILE
%token T_CLINE
%token T_CCOLN

%token T_CFN
%token T_CCLASS
%token T_CMETHOD
%token T_CMODULE

/* gets produced from the lexer if a '@' was scanned before */
%token T_NL

/* this typenames are reserved to avoid ambiguity with 
   user-defined symbols */
%token T_TINT     /* int integer */
%token T_TBOOL    /* bool boolean */
%token T_TFLOAT   /* float double */
%token T_TSTRING  /* string */
%token T_TREGEXP  /* regexp */

/* error token gets produced from the lexer if a regexp 
   was requested but the scan failed */
%token T_INVL

/* end-token */
%token T_END

/* synchronizing token (not a real token, just for "smarter" error-recovery) */
%token T_SYNC

%%

start
  : /* empty */ { $$ = null; }
  | unit        { $$ = $1; }
  ;
  
unit
  : module
    { 
      $$ = @Unit($1); 
      $this->eat_end(); 
    }
  | content
    { 
      $$ = @Unit($1); 
      $this->eat_end(); 
    }
  ;
  
module
  : T_MODULE name ';' content { $$ = @Module($2, $4); }
  ;
  
content
  : uses toplvl { $$ = @Content($1, $2); }
  | toplvl      { $$ = @Content(null, $1); }
  ;
  
uses
  : use      { $$ = [ $1 ]; }
  | uses use { $1[] = $2; $$ = $1; }
  ;
  
use
  : T_USE use_name ';'               
    { 
      $$ = $2; 
      $this->eat_semis(); 
    }
  | T_USE use_name T_AS ident ';'    
    { 
      $$ = @UseAlias($2, $4); 
      $this->eat_semis(); 
    }
  | T_USE use_name '{' use_items comma_opt '}' ';'
    { 
      $$ = @UseUnpack($2, $4); 
      $this->eat_semis(); 
    }
  | T_USE '{' use_items comma_opt '}' ';'
    {
      $$ = @UseUnpack(null, $3);
      $this->eat_semis();
    }
  ;
  
use_items
  : use_item               { $$ = [ $1 ]; }
  | use_items ',' use_item { $1[] = $3; $$ = $1; }
  ;
  
use_item
  : use_name                   { $$ = $1; }
  | use_name T_AS ident        { $$ = @UseAlias($1, $3); }
  | use_name '{' use_items '}' { $$ = @UseUnpack($1, $3); }
  ;

use_name
  : name { $$ = $1; }
  ;

toplvl
  : topex        { $$ = [ $1 ]; }
  | toplvl topex { $1[] = $2; $$ = $1; }
  ;
 
topex
  : module_nst     { $$ = $1; }
  | '@' error T_NL { $$ = null; }
  | attr_decl      { $$ = $1; }
  | enum_decl      { $$ = $1; }
  | class_decl     { $$ = $1; }
  | trait_decl     { $$ = $1; }
  | iface_decl     { $$ = $1; }
  | topex_attr     { $$ = $1; }
  | fn_decl        { $$ = $1; }
  | var_decl       { $$ = $1; }
  | require        { $$ = $1; }
  | error T_SYNC   { $$ = null; }
  | T_END          { $$ = null; }
  | label_decl     { $$ = $1; }
  | alias_decl     { $$ = $1; }
  | stmt           { $$ = $1; }
  ;
  
label_decl
  : ident ':' comp { $$ = @LabelDecl($1, $3); }
  ;

alias_decl
  : T_ALIAS ident '=' name ';' { $$ = @AliasDecl($2, $4); }
  ;

module_nst
  : T_MODULE name '{' '}'
    {
      $$ = @Module($2, null);
      $this->eat_semis();
    }
  | T_MODULE name '{' content '}' 
    { 
      $$ = @Module($2, $4); 
      $this->eat_semis(); 
    }
  | T_MODULE '{' '}'
    {
      $$ = @Module(null, null);
      $this->eat_semis();
    }
  | T_MODULE '{' content '}'      
    { 
      $$ = @Module(null, $3); 
      $this->eat_semis(); 
    }
  ;
  
require
  : T_REQUIRE rxpr ';'       
    { 
      $$ = @RequireDecl(false, $2); 
      $this->eat_semis(); 
    }
  | T_REQUIRE T_PHP rxpr ';' 
    { 
      $$ = @RequireDecl(true, $3); 
      $this->eat_semis(); 
    }
  ;

attr_decl
  : '@' attr_def ';' T_NL 
    { 
      $$ = @AttrDecl($2); 
      $this->eat_semis(); 
    }
  ;

comp_attr
  : '@' attr_def T_NL comp { $$ = @CompAttr($2, $4); }
  ;
  
topex_attr
  : '@' attr_def T_NL topex { $$ = @TopexAttr($2, $4); }
  ;
  
attr_def
  : attr_items comma_opt { $$ = @AttrDef($1); }
  ;
  
attr_items
  : attr_item                { $$ = [ $1 ]; }
  | attr_items ',' attr_item { $1[] = $3; $$ = $1; }
  ;
  
attr_item
  : ident                  { $$ = @AttrItem($1, null); }
  | ident '(' attr_val ')' { $$ = @AttrItem($1, $3); }
  ;
  
attr_val
  : ident                  { $$ = @AttrVal($1, null); }
  | ident '=' lit          { $$ = @AttrVal($1, $3); }
  | ident '(' attr_val ')' { $$ = @AttrVal($1, $3); }
  ;

mods_opt
  : /* empty */ { $$ = null; }
  | mods        { $$ = $1; }
  ;
  
mods
  : mod      { $$ = [ $1 ]; }
  | mods mod { $1[] = $2; $$ = $1; }
  ;
  
mod
  : T_CONST     { $$ = $1; }
  | T_FINAL     { $$ = $1; }
  | T_GLOBAL    { $$ = $1; }
  | T_STATIC    { $$ = $1; }
  | T_PUBLIC    { $$ = $1; }
  | T_PRIVATE   { $$ = $1; }
  | T_PROTECTED { $$ = $1; }
  | T_SEALED    { $$ = $1; }
  | T_INLINE    { $$ = $1; }
  | T_EXTERN    { $$ = $1; }
  ;

enum_decl
  : mods_opt T_ENUM '{' enum_vars comma_opt '}' 
    { 
      $$ = @EnumDecl($1, $4); 
      $this->eat_semis(); 
    }
  ;

enum_vars
  : enum_var               { $$ = [ $1 ]; }
  | enum_vars ',' enum_var { $1[] = $3; $$ = $1; }
  ;
  
enum_var
  : ident          { $$ = @EnumVar($1, null); }
  | ident '=' rxpr { $$ = @EnumVar($1, $3); }
  ;
   
class_decl
  : mods_opt T_CLASS ident ext_opt impl_opt '{' members_opt '}'
    { 
      $$ = @ClassDecl($1, $3, $4, $5, $7); 
      $this->eat_semis(); 
    }
  | mods_opt T_CLASS ident ext_opt impl_opt ';'               
    { 
      $$ = @ClassDecl($1, $3, $4, $5, null); 
      $this->eat_semis(); 
    }
  ;
  
ext_opt
  : /* empty */   { $$ = null; }
  | ':' ext       { $$ = $2; }
  ;
  
exts_opt
  : /* empty */   { $$ = null; }
  | ':' exts      { $$ = $2; }
  ;
  
exts
  : ext          { $$ = [ $1 ]; }
  | exts ',' ext { $1[] = $3; $$ = $1; }
  ;
  
ext
  : name { $$ = $1; }
  ;
  
impl_opt
  : /* empty */ { $$ = null; }
  | '~' impls   { $$ = $2; }
  | '~' error   { $$ = null; }
  ;
  
impls
  : impl           { $$ = [ $1 ]; }
  | impls ',' impl { $1[] = $3; $$ = $1; }
  ;
  
impl
  : name { $$ = $1; }
  ;
  
members_opt
  : /* empty */ { $$ = []; }
  | members     { $$ = $1; }
  ;
  
members
  : member         { $$ = [ $1 ]; }
  | members member { $1[] = $2; $$ = $1; }
  ;
  
member
  : fn_decl                            { $$ = $1; }
  | var_decl                           { $$ = $1; }
  | enum_decl                          { $$ = $1; }
  | alias_decl                         { $$ = $1; }
  | trait_usage                        { $$ = $1; }
  | member_attr                        { $$ = $1; }
  | mods_opt T_NEW pparams ';'         
    { 
      $$ = @CtorDecl($1, $3, null); 
      $this->eat_semis(); 
    }
  | mods_opt T_NEW pparams block       { $$ = @CtorDecl($1, $3, $4); }
  | mods_opt T_DEL pparams ';'         
    { 
      $$ = @DtorDecl($1, $3, null); 
      $this->eat_semis(); 
    }
  | mods_opt T_DEL pparams block       { $$ = @DtorDecl($1, $3, $4); }
  | T_GET ident pparams block          { $$ = @GetterDecl($2, $3, $4); }
  | T_GET ident pparams T_ARR rxpr ';' 
    { 
      $$ = @GetterDecl($2, $3, $5); 
      $this->eat_semis(); 
    }
  | T_SET ident pparams block          { $$ = @SetterDecl($2, $3, $4); }
  | T_SET ident pparams T_ARR rxpr ';' 
    { 
      $$ = @GetterDecl($2, $3, $5); 
      $this->eat_semis(); 
    }
  | mods '{' members_opt '}'           { $$ = @NestedMods($1, $3); }
  ;

member_attr
  : '@' attr_def T_NL fn_decl  { $$ = @MemberAttr($1, $3); }
  | '@' attr_def T_NL var_decl { $$ = @MemberAttr($1, $3); }
  ;
  
trait_usage
  : T_USE name ';'                       
    { 
      $$ = @TraitUse($2, null, null); 
      $this->eat_semis(); 
    }
  | T_USE name T_AS ident ';'            
    { 
      $$ = @TraitUse($2, $4, null); 
      $this->eat_semis(); 
    }
  | T_USE name '{' trait_usage_items '}' { $$ = @TraitUse($2, null, $4); }
  ;
  
trait_usage_items
  : trait_usage_item                   { $$ = [ $1 ]; }
  | trait_usage_items trait_usage_item { $1[] = $2; $$ = $1; }
  ;
  
trait_usage_item
  : mods_opt ident ';'                     
    { 
      $$ = @TraitItem($1, $2, null, null); 
      $this->eat_semis(); 
    }
  | mods_opt ident T_AS mods_opt ident ';' 
    { 
      $$ = @TraitItem($1, $2, $4, $6); 
      $this->eat_semis(); 
    }
  ;
  
trait_decl
  : mods_opt T_TRAIT ident '{' members_opt '}' 
    { 
      $$ = @TraitDecl($1, $3, $5); 
      $this->eat_semis(); 
    }
  | mods_opt T_TRAIT ident ';'  /* allowed? */
    { 
      $$ = @TraitDecl($1, $3, null); 
      $this->eat_semis(); 
    }
  ;

iface_decl
  : mods_opt T_IFACE ident exts_opt '{' members_opt '}' 
    { 
      $$ = @IfaceDecl($1, $3, $4, $6); 
      $this->eat_semis(); 
    }
  | mods_opt T_IFACE ident exts_opt ';' /* allowed? */                
    { 
      $$ = @IfaceDecl($1, $3, $4, null); 
      $this->eat_semis(); 
    } 
  ;

vars
  : var          { $$ = [ $1 ]; }
  | vars ',' var { $1[] = $3; $$ = $1; }
  ;
  
var
  : ident             { $$ = @VarItem($1, null, false); }
  | ident '=' rxpr    { $$ = @VarItem($1, $3, false); }
  | ident T_AREF nxpr { $$ = @VarItem($1, $3, true); }
  ;

vars_noin
  : var_noin               { $$ = [ $1 ]; }
  | vars_noin ',' var_noin { $1[] = $3; $$ = $1; }
  ;
  
var_noin
  : ident               { $$ = @VarItem($1, null, false); }
  | ident '=' rxpr_noin { $$ = @VarItem($1, $3, false); }
  | ident T_AREF nxpr   { $$ = @VarItem($1, $3, true); }
  ;

inner
  : comp       { $$ = [ $1 ]; }
  | inner comp { $1[] = $2; $$ = $1; }
  ;

comp
  : fn_decl        { $$ = $1; }
  | var_decl       { $$ = $1; }
  | label_decl     { $$ = $1; }
  | comp_attr      { $$ = $1; }
  | stmt           { $$ = $1; }
  | '@' error T_NL { $$ = null; }
  | error T_SYNC   { $$ = null; }
  ;

var_decl
  :  T_LET vars ';' 
    { 
      $$ = @VarDecl(null, $2); 
      $this->eat_semis(); 
    }
  | mods vars ';' 
    { 
      $$ = @VarDecl($1, $2); 
      $this->eat_semis(); 
    }
  ;
    
var_decl_noin_nosemi
  : T_LET vars_noin { $$ = @VarDecl(null, $2); }
  | mods vars_noin  { $$ = @VarDecl($1, $2); }
  ;

fn_decl
  : mods_opt T_FN ident pparams fn_decl_body 
    {
      $$ = @FnDecl($1, $3, $4, $5); 
    }
  | mods_opt T_FN ident pparams ';'
    { 
      $$ = @FnDecl($1, $3, $4, null); 
      $this->eat_semis(); 
    }
  | mods_opt T_FN ident ';'
    { 
      $$ = @FnDecl($1, $3, null, null); 
      $this->eat_semis(); 
    }
  ;
  
fn_decl_body
  : block          { $$ = $1; }
  | T_ARR rxpr ';' 
    { 
      $$ = $2;
      $this->eat_semis(); 
    }
  ;
  
fn_expr
  : T_FN ident pparams fn_expr_body { $$ = @FnExpr($2, $3, $4); }
  | T_FN       pparams fn_expr_body { $$ = @FnExpr(null, $2, $3); }
  ;
  
fn_expr_noin
  : T_FN ident pparams fn_expr_body_noin { $$ = @FnExpr($2, $3, $4); }
  | T_FN       pparams fn_expr_body_noin { $$ = @FnExpr(null, $2, $3); }
  ;
  
fn_expr_body
  : block      { $$ = $1; }
  | T_ARR rxpr { $$ = $2; }
  ;
  
fn_expr_body_noin
  : block           { $$ = $1; }
  | T_ARR rxpr_noin { $$ = $2; }
  ;

pparams
  : '(' ')'        { $$ = null; }
  | '(' error ')'  { $$ = null; }
  | '(' params ')' { $$ = $2; }
  ;
  
params
  : param            { $$ = [ $1 ]; }
  | params ',' param { $1[] = $3; $$ = $1; }
  ;
  
param
  : ident                              { $$ = @Param(false, null, null, $1, null, false); }
  | ident '?'                          { $$ = @Param(false, null, null, $1, null, true); }
  | ident '=' rxpr                     { $$ = @Param(false, null, null, $1, $3, false); }
  | hint ident                         { $$ = @Param(false, null, $1, $2, null, false); }
  | hint ident '?'                     { $$ = @Param(false, null, $1, $2, null, true); }
  | hint ident '=' rxpr                { $$ = @Param(false, null, $1, $2, $4, false); }
  | mods ident                         { $$ = @Param(false, $1, null, $2, null, false); }
  | mods ident '?'                     { $$ = @Param(false, $1, null, $2, null, true); }
  | mods ident '=' rxpr                { $$ = @Param(false, $1, null, $2, $4, false); }
  | mods hint ident                    { $$ = @Param(false, $1, $2, $3, null, false); }
  | mods hint ident '?'                { $$ = @Param(false, $1, $2, $3, null, true); }
  | mods hint ident '=' rxpr           { $$ = @Param(false, $1, $2, $3, $5, false); }
  | hint_opt T_THIS dot_ident          { $$ = @ThisParam($1, $3, null); }
  | hint_opt T_THIS dot_ident '=' rxpr { $$ = @ThisParam($1, $3, $5); }
  | hint_opt T_REST ident              { $$ = @RestParam($1, $3); }
  | '&' ident                          { $$ = @Param(true, null, null, $2, null, false); }
  | hint '&' ident                     { $$ = @Param(true, null, $1, $3, null, false); }
  | mods '&' ident                     { $$ = @Param(true, $1, null, $3, null, false); }
  | mods hint '&' ident                { $$ = @Param(true, $1, $2, $4, null, false); }
  ;
  
hint_opt
  : /* empty */ { $$ = null; }
  | hint        { $$ = $1; }
  ;
  
hint
  : type_name { $$ = $1; }
  ;
  
stmt
  : block                                             { $$ = $1; }
  | T_DO stmt T_WHILE pxpr ';'                        { $$ = @DoStmt($2, $4); }
  | T_IF pxpr stmt elsifs_opt else_opt                { $$ = @IfStmt($2, $3, $4, $5); }
  | T_FOR '(' for_in_pair T_IN rxpr ')' stmt          { $$ = @ForInStmt($3, $5, $7); }
  | T_FOR '(' for_expr_noin for_expr ')' stmt         { $$ = @ForStmt($3, $4, null, $6); }
  | T_FOR '(' for_expr_noin for_expr rseq ')' stmt    { $$ = @ForStmt($3, $4, $5, $7); }
  | T_FOR '(' error ')' stmt                          { $$ = null; }
  | T_FOR block                                       { $$ = @ForStmt(null, null, null, $2); }
  | T_TRY block                                       { $$ = @TryStmt($2, null, null); }
  | T_TRY block catches                               { $$ = @TryStmt($2, $3, null); }
  | T_TRY block finally                               { $$ = @TryStmt($2, null, $3); }
  | T_TRY block catches finally                       { $$ = @TryStmt($2, $3, $4); }
  | T_PHP '{' php_usage_opt str '}'                   { $$ = @PhpStmt($3, $4); }
  | T_GOTO ident ';'                                  { $$ = @GotoStmt($2); }
  | T_TEST block                                      { $$ = @TestStmt(null, $2); }
  | T_TEST str block                                  { $$ = @TestStmt($2, $3); }
  | T_BREAK ';'                                       { $$ = @BreakStmt(null); }
  | T_BREAK ident ';'                                 { $$ = @BreakStmt($2); }
  | T_CONTINUE ';'                                    { $$ = @ContinueStmt(null); }
  | T_CONTINUE ident ';'                              { $$ = @ContinueStmt($2); }
  | T_PRINT rseq ';'                                  { $$ = @PrintStmt($2); }
  | T_THROW rxpr ';'                                  { $$ = @ThrowStmt($2); }
  | T_WHILE pxpr stmt                                 { $$ = @WhileStmt($2, $3); }
  | T_ASSERT rxpr ';'                                 { $$ = @AssertStmt($2, null); }
  | T_ASSERT rxpr ':' str ';'                         { $$ = @AssertStmt($2, $4); }
  | T_SWITCH pxpr '{' cases '}'                       { $$ = @SwitchStmt($2, $4); }
  | T_RETURN rxpr ';'                                 { $$ = @ReturnStmt($2); }
  | T_RETURN ';'                                      { $$ = @ReturnStmt(null); }
  | lxpr_stmt                                         { $$ = $1; }
  | error ';'                                         { $$ = null; }
  ;
  
for_in_pair
  : ident           { $$ = @ForInPair(null, $1); }
  | ident ':' ident { $$ = @ForInPair($1, $3); }
  ;
  
for_expr
  : rxpr_stmt { $$ = $1; }                 
  ;
  
for_expr_noin
  : rxpr_stmt_noin           { $$ = $1; }
  | var_decl_noin_nosemi ';' { $$ = $1; }
  ;
  
elsifs_opt
  : /* empty */ { $$ = null; }
  | elsifs      { $$ = $1; }
  ;
  
elsifs
  : elsif        { $$ = [ $1 ]; }
  | elsifs elsif { $1[] = $2; $$ = $1; }
  ;
  
elsif
  : T_ELSIF pxpr stmt { $$ = @ElsifItem($2, $3); }
  ;
  
else_opt
  : /* empty */ { $$ = null; }
  | else        { $$ = $1; }
  ;  

else
  : T_ELSE stmt { $$ = @ElseItem($2); }
  ;
  
catches
  : catch         { $$ = [ $1 ]; }
  | catches catch { $1[] = $2; $$ = $1; }
  ;
  
catch
  : T_CATCH block                        { $$ = @CatchItem(null, null, $2); }
  | T_CATCH '(' name ')' block           { $$ = @CatchItem($3, null, $5); }
  | T_CATCH '(' name ':' ident ')' block { $$ = @CatchItem($3, $5, $7); }
  | T_CATCH '(' error ')' block          { $$ = null; }
  ;
  
finally
  : T_FINALLY block { $$ = @FinallyItem($2); }
  ;
  
php_usage_opt
  : /* empty */ { $$ = null; }
  | php_usage   { $$ = $1; }
  ;
  
php_usage
  : php_use           { $$ = [ $1 ]; }
  | php_usage php_use { $1[] = $2; $$ = $1; }
  ;
  
php_use
  : T_USE php_use_items ';' { $$ = @PhpUse($2); }
  ;
  
php_use_items
  : php_use_item                   { $$ = [ $1 ]; }
  | php_use_items ',' php_use_item { $1[] = $3; $$ = $1; }
  ;
  
php_use_item
  : ident            { $$ = @PhpUseItem($1, null); }
  | ident T_AS ident { $$ = @PhpUseItem($1, $3); }
  ;

cases
  : case       { $$ = [ $1 ]; }
  | cases case { $1[] = $2; $$ = $1; }
  ;
  
case
  : casels inner { $$ = @CaseItem($1, $2); }
  ;
  
casels
  : casel        { $$ = [ $1 ]; }
  | casels casel { $1[] = $2; $$ = $1; }
  ;
  
casel
  : T_CASE rxpr ':' { $$ = @CaseLabel($2); }
  | T_DEFAULT ':'   { $$ = @CaseLabel(null); }
  ;
  
block
  : '{' '}'       { $$ = @Block(null); }
  | '{' inner '}' { $$ = @Block($2); }
  | '{' error '}' { $$ = null; }
  ;
 
lxpr_stmt
  : lseq ';' 
    { 
      $$ = @ExprStmt($1); 
      $this->eat_semis(); 
    }
  | ';' 
    { 
      $$ = @ExprStmt(null); 
      $this->eat_semis(); 
    }
  ;

rxpr_stmt
  : rseq ';' { $$ = @ExprStmt($1); }
  | ';'      { $$ = @ExprStmt(null); }
  ;

rxpr_stmt_noin
  : rseq_noin ';' { $$ = @ExprStmt($1); }
  | ';'           { $$ = @ExprStmt(null); }
  ;

lseq
  : lxpr          { $$ = [ $1 ]; }
  | lseq ',' rxpr { $1[] = $3; $$ = $1; }
  ;

rseq
  : rxpr          { $$ = [ $1 ]; }
  | rseq ',' rxpr { $1[] = $3; $$ = $1; }
  ;
  
rseq_noin
  : rxpr_noin               { $$ = [ $1 ]; }
  | rseq_noin ',' rxpr_noin { $1[] = $3; $$ = $1; }
  ;
 
/* left expression */
/* this kind of expression can start a expression-statement. */
/* excluded variants are `obj` and `fn_expr` due to ambiguity */
  
lxpr
  : lxpr '+' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | lxpr '-' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | lxpr '*' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | lxpr '/' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | lxpr '%' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_POW rxpr         { $$ = @BinExpr($1, $2, $2); }
  | lxpr '~' rxpr %prec '+' { $$ = @BinExpr($1, $2, $3); }
  | lxpr '&' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | lxpr '|' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | lxpr '^' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | lxpr '<' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | lxpr '>' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_GTE rxpr         { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_LTE rxpr         { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_BOOL_AND rxpr    { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_BOOL_OR rxpr     { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_BOOL_XOR rxpr    { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_RANGE rxpr       { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_SL rxpr          { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_SR rxpr          { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_EQ rxpr          { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_NEQ rxpr         { $$ = @BinExpr($1, $2, $3); } 
  | lxpr T_IN rxpr          { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_NIN rxpr         { $$ = @BinExpr($1, $2, $3); }
  | lxpr T_IS type_name     { $$ = @CheckExpr($1, $2, $3); }
  | lxpr T_NIS type_name    { $$ = @CheckExpr($1, $2, $3); }
  | lxpr T_AS type_name     { $$ = @CastExpr($1, $3); }
  | lxpr T_INC %prec '.'    { $$ = @UpdateExpr(false, $1, $2); }
  | lxpr T_DEC %prec '.'    { $$ = @UpdateExpr(false, $1, $2); }
  | lxpr '=' rxpr           { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_AREF nxpr        { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_APLUS rxpr       { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_AMINUS rxpr      { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_AMUL rxpr        { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_ADIV rxpr        { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_AMOD rxpr        { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_APOW rxpr        { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_ACONCAT rxpr     { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_ABIT_OR rxpr     { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_ABIT_AND rxpr    { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_ABIT_XOR rxpr    { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_ABOOL_OR rxpr    { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_ABOOL_AND rxpr   { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_ABOOL_XOR rxpr   { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_ASHIFT_L rxpr    { $$ = @AssignExpr($1, $2, $3); }
  | lxpr T_ASHIFT_R rxpr    { $$ = @AssignExpr($1, $2, $3); }
  | lxpr dot_ident          { $$ = @MemberExpr(true, false, $1, $2); }
  | lxpr '.' '{' rxpr '}'   { $$ = @MemberExpr(true, true, $1, $4); }
  | lxpr '[' rxpr ']'       { $$ = @MemberExpr(false, true, $1, $3); }
  | lxpr '[' error ']'      { $$ = null; }
  | lxpr '?' rxpr ':' rxpr  { $$ = @CondExpr($1, $3, $5); } 
  | lxpr '?' ':' rxpr       { $$ = @CondExpr($1, null, $4); }
  | lxpr pargs              { $$ = @CallExpr($1, $2); }
  | T_YIELD rxpr            { $$ = @YieldExpr(null, $2); }
  | T_YIELD rxpr ':' rxpr   { $$ = @YieldExpr($2, $4); }
  | '-' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '+' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '~' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '!' rxpr                { $$ = @UnaryExpr($1, $2); }
  | T_INC rxpr %prec '!'    { $$ = @UpdateExpr(true, $2, $1); }
  | T_DEC rxpr %prec '!'    { $$ = @UpdateExpr(true, $2, $1); }
  | T_NEW type_id           { $$ = @NewExpr($2, null); }
  | T_NEW type_id pargs     { $$ = @NewExpr($2, $3); }
  | T_NEW nxpr              { $$ = @NewExpr($2, null); }
  | T_NEW nxpr pargs        { $$ = @NewExpr($2, $3); }
  | T_DEL nxpr              { $$ = @DelExpr($2); }
  | atom                    { $$ = $1; }
  | legacy_cast             { $$ = $1; }
  ;

/* right expression */
/* this kind of expression covers all valid operations */

rxpr
  : rxpr '+' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr '-' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr '*' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr '/' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr '%' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_POW rxpr         { $$ = @BinExpr($1, $2, $2); }
  | rxpr '~' rxpr %prec '+' { $$ = @BinExpr($1, $2, $3); }
  | rxpr '&' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr '|' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr '^' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr '>' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr '<' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_GTE rxpr         { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_LTE rxpr         { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_BOOL_AND rxpr    { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_BOOL_OR rxpr     { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_BOOL_XOR rxpr    { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_RANGE rxpr       { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_SL rxpr          { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_SR rxpr          { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_EQ rxpr          { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_NEQ rxpr         { $$ = @BinExpr($1, $2, $3); } 
  | rxpr T_IN rxpr          { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_NIN rxpr         { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_IS type_name     { $$ = @CheckExpr($1, $2, $3); }
  | rxpr T_NIS type_name    { $$ = @CheckExpr($1, $2, $3); }
  | rxpr T_AS type_name     { $$ = @CastExpr($1, $3); }
  | rxpr T_INC %prec '.'    { $$ = @UpdateExpr(false, $1, $2); }
  | rxpr T_DEC %prec '.'    { $$ = @UpdateExpr(false, $1, $2); }
  | rxpr '=' rxpr           { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_AREF nxpr        { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_APLUS rxpr       { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_AMINUS rxpr      { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_AMUL rxpr        { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_ADIV rxpr        { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_AMOD rxpr        { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_APOW rxpr        { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_ACONCAT rxpr     { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_ABIT_OR rxpr     { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_ABIT_AND rxpr    { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_ABIT_XOR rxpr    { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_ABOOL_OR rxpr    { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_ABOOL_AND rxpr   { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_ABOOL_XOR rxpr   { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_ASHIFT_L rxpr    { $$ = @AssignExpr($1, $2, $3); }
  | rxpr T_ASHIFT_R rxpr    { $$ = @AssignExpr($1, $2, $3); }
  | rxpr dot_ident          { $$ = @MemberExpr(true, false, $1, $2); }
  | rxpr '.' '{' rxpr '}'   { $$ = @MemberExpr(true, true, $1, $4); }
  | rxpr '[' rxpr ']'       { $$ = @MemberExpr(false, true, $1, $3); }
  | rxpr '[' error ']'      { $$ = null; }
  | rxpr '?' rxpr ':' rxpr  { $$ = @CondExpr($1, $3, $5); } 
  | rxpr '?' ':' rxpr       { $$ = @CondExpr($1, null, $4); }
  | rxpr pargs              { $$ = @CallExpr($1, $2); }
  | T_YIELD rxpr            { $$ = @YieldExpr(null, $2); }
  | T_YIELD rxpr ':' rxpr   { $$ = @YieldExpr($2, $4); }
  | '-' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '+' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '~' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '!' rxpr                { $$ = @UnaryExpr($1, $2); }
  | T_INC rxpr %prec '!'    { $$ = @UpdateExpr(true, $2, $1); }
  | T_DEC rxpr %prec '!'    { $$ = @UpdateExpr(true, $2, $1); }
  | T_NEW type_id           { $$ = @NewExpr($2, null); }
  | T_NEW type_id pargs     { $$ = @NewExpr($2, $3); }
  | T_NEW nxpr              { $$ = @NewExpr($2, null); }
  | T_NEW nxpr pargs        { $$ = @NewExpr($2, $3); }
  | T_DEL nxpr              { $$ = @DelExpr($2); }
  | atom                    { $$ = $1; }
  | obj                     { $$ = $1; }
  | fn_expr                 { $$ = $1; }
  | legacy_cast             { $$ = $1; }
  ;
  
/* right-expression without the in-operator */
/* this kind of expression is used in for-in loops */
  
rxpr_noin
  : rxpr_noin '+' rxpr_noin           { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin '-' rxpr_noin           { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin '*' rxpr_noin           { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin '/' rxpr_noin           { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin '%' rxpr_noin           { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin T_POW rxpr_noin         { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin '~' rxpr_noin %prec '+' { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin '&' rxpr_noin           { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin '|' rxpr_noin           { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin '^' rxpr_noin           { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin '>' rxpr_noin           { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin '<' rxpr_noin           { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin T_GTE rxpr_noin         { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin T_LTE rxpr_noin         { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin T_BOOL_AND rxpr_noin    { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin T_BOOL_OR rxpr_noin     { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin T_BOOL_XOR rxpr_noin    { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin T_RANGE rxpr_noin       { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin T_SL rxpr_noin          { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin T_SR rxpr_noin          { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin T_EQ rxpr_noin          { $$ = @BinExpr($1, $2, $3); }
  | rxpr_noin T_NEQ rxpr_noin         { $$ = @BinExpr($1, $2, $3); } 
  | rxpr_noin T_IS type_name          { $$ = @CheckExpr($1, $2, $3); }
  | rxpr_noin T_NIS type_name         { $$ = @CheckExpr($1, $2, $3); }
  | rxpr_noin T_AS type_name          { $$ = @CastExpr($1, $3); }
  | rxpr_noin T_INC %prec '.'         { $$ = @UpdateExpr(false, $1, $2); }
  | rxpr_noin T_DEC %prec '.'         { $$ = @UpdateExpr(false, $1, $2); }
  | rxpr_noin '=' rxpr_noin           { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_AREF nxpr             { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_APLUS rxpr_noin       { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_AMINUS rxpr_noin      { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_AMUL rxpr_noin        { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_ADIV rxpr_noin        { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_AMOD rxpr_noin        { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_APOW rxpr_noin        { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_ACONCAT rxpr_noin     { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_ABIT_OR rxpr_noin     { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_ABIT_AND rxpr_noin    { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_ABIT_XOR rxpr_noin    { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_ABOOL_OR rxpr_noin    { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_ABOOL_AND rxpr_noin   { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_ABOOL_XOR rxpr_noin   { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_ASHIFT_L rxpr_noin    { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin T_ASHIFT_R rxpr_noin    { $$ = @AssignExpr($1, $2, $3); }
  | rxpr_noin dot_ident               { $$ = @MemberExpr(true, false, $1, $2); }
  | rxpr_noin '.' '{' rxpr '}'        { $$ = @MemberExpr(true, true, $1, $4); }
  | rxpr_noin '[' rxpr ']'            { $$ = @MemberExpr(false, true, $1, $3); }
  | rxpr_noin '[' error ']'           { $$ = null; }
  | rxpr_noin '?' rxpr ':' rxpr_noin  { $$ = @CondExpr($1, $3, $5); } 
  | rxpr_noin '?' ':' rxpr_noin       { $$ = @CondExpr($1, null, $4); }
  | rxpr_noin pargs                   { $$ = @CallExpr($1, $2); }
  | T_YIELD rxpr                      { $$ = @YieldExpr(null, $2); }
  | T_YIELD rxpr ':' rxpr             { $$ = @YieldExpr($2, $4); }
  | '-' rxpr_noin %prec '!'           { $$ = @UnaryExpr($1, $2); }
  | '+' rxpr_noin %prec '!'           { $$ = @UnaryExpr($1, $2); }
  | '~' rxpr_noin %prec '!'           { $$ = @UnaryExpr($1, $2); }
  | '!' rxpr_noin                     { $$ = @UnaryExpr($1, $2); }
  | T_INC rxpr_noin %prec '!'         { $$ = @UpdateExpr(true, $2, $1); }
  | T_DEC rxpr_noin %prec '!'         { $$ = @UpdateExpr(true, $2, $1); }
  | T_NEW type_id                     { $$ = @NewExpr($2, null); }
  | T_NEW type_id pargs               { $$ = @NewExpr($2, $3); }
  | T_NEW nxpr                        { $$ = @NewExpr($2, null); }
  | T_NEW nxpr pargs                  { $$ = @NewExpr($2, $3); }
  | T_DEL nxpr                        { $$ = @DelExpr($2); }
  | atom                              { $$ = $1; }
  | obj                               { $$ = $1; }
  | fn_expr_noin                      { $$ = $1; }
  | legacy_cast_noin                  { $$ = $1; }
  ;
  
legacy_cast
  : '(' type_id ')' rxpr 
    { 
      $$ = @CastExpr($4, $2); 
      $this->error_at($1->loc, ERR_WARN, 'legacy cast, use `expr as type` instead'); 
    }
  ;
  
legacy_cast_noin
  : '(' type_id ')' rxpr_noin 
    { 
      $$ = @CastExpr($4, $2); 
      $this->error_at($1->loc, ERR_WARN, 'legacy cast, use `expr as type` instead'); 
    }
  ;
 
nxpr
  : nxpr dot_ident        { $$ = @MemberExpr(true, false, $1, $2); }
  | nxpr '.' '{' rxpr '}' { $$ = @MemberExpr(true, true, $1, $4); }
  | nxpr '[' rxpr ']'     { $$ = @MemberExpr(false, true, $1, $3); }
  | nxpr '[' error ']'    { $$ = null; }
  | atom                  { $$ = $1; }
  ;
  
pxpr
  : '(' rxpr ')'  { $$ = $2; }
  | '(' error ')' { $$ = null; }
  ;
  
pargs
  : '(' ')'       { $$ = null; }
  | '(' error ')' { $$ = null; }
  | '(' args ')'  { $$ = $2; }
  ;
  
args
  : arg          { $$ = [ $1 ]; }
  | args ',' arg { $1[] = $3; $$ = $1; }
  ;
  
arg
  : rxpr           { $$ = $1; }
  | ident ':' rxpr { $$ = @NamedArg($1, $3); }
  | T_REST rxpr    { $$ = @RestArg($2); }
  ;
  
atom
  : num          { $$ = $1; }
  | reg          { $$ = $1; }
  | arr          { $$ = $1; }
  | name         { $$ = $1; }
  | kwc          { $$ = $1; }
  | str          { $$ = $1; }
  | '(' rseq ')' { $$ = $2; }
  ;
 
reg
  : '/'                { $this->lex->scan_regexp($1); } 
    T_REGEXP %prec '!' { $$ = @RegexpLit($3->value); }
  ;
  
name
  : ident              { $$ = @Name($1, false); }
  | T_DDDOT ident      { $$ = @Name($2, true); }
  | name T_DDDOT ident { $1->add($3); $$ = $1; }
  ;

type_name
  : name { $$ = $1; }
  | type_id { $$ = $1; }
  ;

type_id
  : T_TINT    { $$ = @TypeId($1->type); }
  | T_TBOOL   { $$ = @TypeId($1->type); }
  | T_TFLOAT  { $$ = @TypeId($1->type); }
  | T_TSTRING { $$ = @TypeId($1->type); }
  | T_TREGEXP { $$ = @TypeId($1->type); }
  ;

ident
  : T_IDENT   { $$ = @Ident($1->value); }
  | T_GET     { $$ = @Ident($1->value); }
  | T_SET     { $$ = @Ident($1->value); }
  ;
  
/* this rule allows us to use all kinds of keywords as props in objects */
/* e.g.: `foo.if = 1;` */

dot_ident
  : '.' ident       { $$ = $2; }
  | '.' T_FN        { $$ = @Ident($2->value); }
  | '.' T_LET       { $$ = @Ident($2->value); }
  | '.' T_PHP       { $$ = @Ident($2->value); }    
  | '.' T_TEST      { $$ = @Ident($2->value); }     
  | '.' T_ASSERT    { $$ = @Ident($2->value); }       
  | '.' T_TRUE      { $$ = @Ident($2->value); }     
  | '.' T_FALSE     { $$ = @Ident($2->value); }      
  | '.' T_NULL      { $$ = @Ident($2->value); }     
  | '.' T_IF        { $$ = @Ident($2->value); }   
  | '.' T_ELSIF     { $$ = @Ident($2->value); }     
  | '.' T_ELSE      { $$ = @Ident($2->value); }     
  | '.' T_TRY       { $$ = @Ident($2->value); }    
  | '.' T_THROW     { $$ = @Ident($2->value); }      
  | '.' T_CATCH     { $$ = @Ident($2->value); }      
  | '.' T_FINALLY   { $$ = @Ident($2->value); }        
  | '.' T_USE       { $$ = @Ident($2->value); }    
  | '.' T_MODULE    { $$ = @Ident($2->value); }       
  | '.' T_EXTERN    { $$ = @Ident($2->value); }       
  | '.' T_CLASS     { $$ = @Ident($2->value); } 
  | '.' T_TRAIT     { $$ = @Ident($2->value); }      
  | '.' T_IFACE     { $$ = @Ident($2->value); }      
  | '.' T_THIS      { $$ = @Ident($2->value); }     
  | '.' T_STATIC    { $$ = @Ident($2->value); }       
  | '.' T_CONST     { $$ = @Ident($2->value); }      
  | '.' T_FINAL     { $$ = @Ident($2->value); }      
  | '.' T_PUBLIC    { $$ = @Ident($2->value); }       
  | '.' T_PRIVATE   { $$ = @Ident($2->value); }        
  | '.' T_PROTECTED { $$ = @Ident($2->value); }          
  | '.' T_ENUM      { $$ = @Ident($2->value); }     
  | '.' T_SWITCH    { $$ = @Ident($2->value); }       
  | '.' T_CASE      { $$ = @Ident($2->value); }     
  | '.' T_DEFAULT   { $$ = @Ident($2->value); }        
  | '.' T_FOR       { $$ = @Ident($2->value); }    
  | '.' T_WHILE     { $$ = @Ident($2->value); }      
  | '.' T_DO        { $$ = @Ident($2->value); }   
  | '.' T_BREAK     { $$ = @Ident($2->value); }      
  | '.' T_CONTINUE  { $$ = @Ident($2->value); }         
  | '.' T_RETURN    { $$ = @Ident($2->value); }        
  | '.' T_SUPER     { $$ = @Ident($2->value); }      
  | '.' T_GOTO      { $$ = @Ident($2->value); }     
  | '.' T_REQUIRE   { $$ = @Ident($2->value); }        
  | '.' T_YIELD     { $$ = @Ident($2->value); }      
  | '.' T_GLOBAL    { $$ = @Ident($2->value); }       
  | '.' T_TINT      { $$ = @Ident($2->value); }     
  | '.' T_TBOOL     { $$ = @Ident($2->value); }      
  | '.' T_TFLOAT    { $$ = @Ident($2->value); }       
  | '.' T_TSTRING   { $$ = @Ident($2->value); }
  | '.' T_TREGEXP   { $$ = @Ident($2->value); }
  | '.' T_SEALED    { $$ = @Ident($2->value); }
  | '.' T_INLINE    { $$ = @Ident($2->value); }
  | '.' T_CDIR      { $$ = @Ident($2->value); }
  | '.' T_CFILE     { $$ = @Ident($2->value); }
  | '.' T_CLINE     { $$ = @Ident($2->value); }
  | '.' T_CCOLN     { $$ = @Ident($2->value); }
  | '.' T_CFN       { $$ = @Ident($2->value); }
  | '.' T_CCLASS    { $$ = @Ident($2->value); }
  | '.' T_CMETHOD   { $$ = @Ident($2->value); }
  | '.' T_CMODULE   { $$ = @Ident($2->value); }
  ;
 
kwc
  : T_THIS    { $$ = @ThisExpr; }
  | T_SUPER   { $$ = @SuperExpr; }
  | T_NULL    { $$ = @NullLit; }
  | T_TRUE    { $$ = @TrueLit; }
  | T_FALSE   { $$ = @FalseLit; }
  | T_CDIR    { $$ = @StrLit($this->cdir, 'c'); }
  | T_CFILE   { $$ = @StrLit($this->cfile, 'c'); }
  | T_CLINE   { $$ = @StrLit($1->loc->pos->line, 'c'); }
  | T_CCOLN   { $$ = @StrLit($1->loc->pos->coln, 'c'); }
  | T_CFN     { $$ = @EngineConst($1->type); }
  | T_CCLASS  { $$ = @EngineConst($1->type); }
  | T_CMETHOD { $$ = @EngineConst($1->type); }
  | T_CMODULE { $$ = @EngineConst($1->type); }
  ;
 
str
  : T_STRING { $$ = @StrLit($1->value, $1->flag); }
  ;
  
num
  : T_LNUM { $$ = @LNumLit($1->value); }
  | T_DNUM { $$ = @DNumLit($1->value); }
  | T_SNUM { $$ = @SNumLit($1->value, $1->suffix); }
  ;

lit
  : str   { $$ = $1; }
  | num   { $$ = $1; }
  | reg   { $$ = $1; }
  | arr   { $$ = $1; }
  | obj   { $$ = $1; }
  | kwc   { $$ = $1; }
  | ident { $$ = $1; }
  ;

arr
  : '[' ']'                                        { $$ = @ArrLit(null); }
  | '[' rxpr T_FOR '(' rxpr_noin T_IN rxpr ')' ']' { $$ = @ArrGen($2, $5, $7); }
  | '[' arr_vals ']'                               { $$ = @ArrLit($2); }
  | '[' error ']'                                  { $$ = null; }
  ;
  
arr_vals
  : arr_vals_cs comma_opt { $$ = $1; }
  ;
  
arr_vals_cs
  : arr_val                 { $$ = [ $1 ]; }
  | arr_vals_cs ',' arr_val { $1[] = $3; $$ = $1; }
  ;
  
arr_val
  : T_REST rxpr { $$ = @SpreadExpr($2); }
  | rxpr        { $$ = $1; }
  ;
  
obj
  : '{' '}'           { $$ = @ObjLit(null); }
  | '{' obj_pairs '}' { $$ = @ObjLit($2); }
  | '{' error '}'     { $$ = null; }
  ;
    
obj_pairs
  : obj_pairs_cs comma_opt { $$ = $1; }
  ;
  
obj_pairs_cs
  : obj_pair                  { $$ = [ $1 ]; }
  | obj_pairs_cs ',' obj_pair { $1[] = $3; $$ = $1; }
  ;
  
obj_pair
  : obj_key ':' rxpr { $$ = @ObjPair($1, $3); }
  ;
  
obj_key
  : ident        { $$ = $1; }
  | str          { $$ = $1; }
  | '(' rxpr ')' { $$ = $2; }
  | T_FN         { $$ = @Ident($1->value); }
  | T_LET        { $$ = @Ident($1->value); }
  | T_PHP        { $$ = @Ident($1->value); }    
  | T_TEST       { $$ = @Ident($1->value); }     
  | T_ASSERT     { $$ = @Ident($1->value); }       
  | T_TRUE       { $$ = @Ident($1->value); }     
  | T_FALSE      { $$ = @Ident($1->value); }      
  | T_NULL       { $$ = @Ident($1->value); }     
  | T_IF         { $$ = @Ident($1->value); }   
  | T_ELSIF      { $$ = @Ident($1->value); }     
  | T_ELSE       { $$ = @Ident($1->value); }     
  | T_TRY        { $$ = @Ident($1->value); }    
  | T_THROW      { $$ = @Ident($1->value); }      
  | T_CATCH      { $$ = @Ident($1->value); }      
  | T_FINALLY    { $$ = @Ident($1->value); }        
  | T_USE        { $$ = @Ident($1->value); }    
  | T_MODULE     { $$ = @Ident($1->value); }       
  | T_EXTERN     { $$ = @Ident($1->value); }       
  | T_CLASS      { $$ = @Ident($1->value); }      
  | T_TRAIT      { $$ = @Ident($1->value); }      
  | T_IFACE      { $$ = @Ident($1->value); }      
  | T_THIS       { $$ = @Ident($1->value); }     
  | T_STATIC     { $$ = @Ident($1->value); }       
  | T_CONST      { $$ = @Ident($1->value); }      
  | T_FINAL      { $$ = @Ident($1->value); }      
  | T_PUBLIC     { $$ = @Ident($1->value); }       
  | T_PRIVATE    { $$ = @Ident($1->value); }        
  | T_PROTECTED  { $$ = @Ident($1->value); }          
  | T_ENUM       { $$ = @Ident($1->value); }     
  | T_SWITCH     { $$ = @Ident($1->value); }       
  | T_CASE       { $$ = @Ident($1->value); }            
  | T_FOR        { $$ = @Ident($1->value); }    
  | T_WHILE      { $$ = @Ident($1->value); }      
  | T_DO         { $$ = @Ident($1->value); }   
  | T_BREAK      { $$ = @Ident($1->value); }      
  | T_CONTINUE   { $$ = @Ident($1->value); }         
  | T_RETURN     { $$ = @Ident($1->value); }        
  | T_SUPER      { $$ = @Ident($1->value); }      
  | T_GOTO       { $$ = @Ident($1->value); }     
  | T_REQUIRE    { $$ = @Ident($1->value); }        
  | T_YIELD      { $$ = @Ident($1->value); }      
  | T_GLOBAL     { $$ = @Ident($1->value); }       
  | T_TINT       { $$ = @Ident($1->value); }     
  | T_TBOOL      { $$ = @Ident($1->value); }      
  | T_TFLOAT     { $$ = @Ident($1->value); }       
  | T_TSTRING    { $$ = @Ident($1->value); } 
  | T_TREGEXP    { $$ = @Ident($1->value); }
  | T_SEALED     { $$ = @Ident($1->value); }
  | T_INLINE     { $$ = @Ident($1->value); }
  | T_CDIR       { $$ = @Ident($1->value); }
  | T_CFILE      { $$ = @Ident($1->value); }
  | T_CLINE      { $$ = @Ident($1->value); }
  | T_CCOLN      { $$ = @Ident($1->value); }
  | T_CFN        { $$ = @Ident($1->value); }
  | T_CCLASS     { $$ = @Ident($1->value); }
  | T_CMETHOD    { $$ = @Ident($1->value); }
  | T_CMODULE    { $$ = @Ident($1->value); }
  ;
  
comma_opt
  : /* empty */
  | ','
  ;
  
%%