%pure_parser

/* this grammar does not produce a 100% valid syntax-tree.
   but that's okay, the tree gets validated in a later step */

/* 
  shift/reduce conflicts:
   - 1x if-elsif resolved by shift
   - 1x elsif-elsif resolved by shift
   - 1x if-else resolved by shift
   - 1x '<' in generic type-names resolved by shift
*/ 
%expect 4

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
%right T_AS 
%right T_REST
%right T_DEL
%right T_INC T_DEC
%right '!' 
%right T_POW
%nonassoc T_NEW
%left '.' '[' ']'
%nonassoc '(' ')'
%nonassoc T_DDDOT

%token T_FN
%token T_LET
%token T_USE
%token T_ENUM
%token T_TYPE
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
%token T_SELF

%token T_GET
%token T_SET

%token T_DO
%token T_IF
%token T_ELIF
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
%token T_UNSAFE
%token T_NATIVE
%token T_HIDDEN
%token T_REWRITE

%token T_PHP
%token T_TEST

/* gets produced from the lexer if a '@' was scanned before */
%token T_NL

/* gets produced from the lexer if string-interpolation was found */
%token T_SUBST

/* this typenames are reserved to avoid ambiguity with 
   user-defined symbols */
%token T_TINT   /* int */
%token T_TBOOL  /* bool */
%token T_TFLOAT /* float, dbl */
%token T_TSTR   /* str */
%token T_TTUP   /* tup */
%token T_TDEC   /* dec */
%token T_TANY   /* any */

/* parse-time constants */
%token T_CDIR
%token T_CFILE
%token T_CLINE
%token T_CCOLN
%token T_CFN
%token T_CCLASS
%token T_CTRAIT
%token T_CMETHOD
%token T_CMODULE

/* error token gets produced from the lexer if a regexp 
   was requested but the scan failed */
%token T_INVL

/* end-token */
%token T_END

%%

start
  : unit { $$ = $1; }
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
  | /* empty */
    {
      $$ = @Unit(null);
      $this->eat_end();
    }
  ;
  
module
  : T_MODULE name ';' content 
    { 
      $$ = @Module($2, null, $4); 
      $this->eat_semis();
    }
  ;
 
content
  : toplvl { $$ = @Content($1); }
  ;
  
toplvl
  : topex        { $$ = [ $1 ]; }
  | toplvl topex { $1[] = $2; $$ = $1; }
  ;
 
topex
  : module_nst     { $$ = $1; }
  //| attrs ';'      { $$ = $1; }
  | use_decl       { $$ = $1; }
  | enum_decl      { $$ = $1; }
  | type_decl      { $$ = $1; }
  | class_decl     { $$ = $1; }
  | trait_decl     { $$ = $1; }
  | iface_decl     { $$ = $1; }
  | fn_decl        { $$ = $1; }
  | var_decl       { $$ = $1; }
  | require_decl   { $$ = $1; }
  | T_END          { $$ = null; }
  | label_decl     { $$ = $1; }
  | stmt           { $$ = $1; }
  ;
   
use_decl
  : T_USE use_item ';'
    { 
      $$ = @UseDecl($2, false); 
      $this->eat_semis(); 
    }
  | T_PUBLIC T_USE use_item ';'
    {
      $$ = @UseDecl($3, true);
      $this->eat_semis();
    }
  | T_PRIVATE T_USE use_item ';' /* same as normal use */
    {
      $$ = @UseDecl($3, false); 
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
  
label_decl
  : ident ':' comp { $$ = @LabelDecl($1, $3); }
  ;

require_decl
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

module_nst
  : T_MODULE name '{' '}'
    {
      $$ = @Module($2, null, null);
      $this->eat_semis();
    }
  | T_MODULE name '{' content '}' 
    { 
      $$ = @Module($2, null, $4); 
      $this->eat_semis(); 
    }
  | T_MODULE '{' '}'
    {
      $$ = @Module(null, null, null);
      $this->eat_semis();
    }
  | T_MODULE '{' content '}'      
    { 
      $$ = @Module(null, null, $3); 
      $this->eat_semis(); 
    }
  ;

/*
attrs 
  : '@' '[' attr_items ']' { $$ = $3; }
  ;

attr_items
  : attr_item                { $$ = [ $1 ]; }
  | attr_items ',' attr_item { $1[] = $3; $$ = $1; }
  ;

attr_item
  : aid                    { $$ = $1; }
  | aid '=' str            { $$ = $1; }
  | aid '(' attr_items ')' { $$ = [ $1, $3 ]; }
  ;
*/

mods_opt
  : /* empty */ { $$ = null; }
  //| attrs mods  { $$ = [ $1, $2 ]; }
  //| attrs       { $$ = [ $1, null ]; }
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
  | T_UNSAFE    { $$ = $1; }
  | T_NATIVE    { $$ = $1; }
  | T_HIDDEN    { $$ = $1; }
  ;

rewrite_opt
  : /* empty */ { $$ = null; }
  | rewrite     { $$ = $1; }
  ;
  
rewrite
  : T_REWRITE '(' rewrite_args ')'
  ;
  
rewrite_args
  : rewrite_arg
  | rewrite_args ',' rewrite_arg
  ;
  
rewrite_arg
  : '$' T_LNUM
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
  
type_decl
  : mods_opt T_TYPE type_name ident ';' { $$ = null; }
  ;
   
class_decl
  : mods_opt T_CLASS ident gen_defs_opt ext_opt impl_opt '{' trait_uses_opt members_opt '}'
    { 
      $$ = @ClassDecl($1, $3, $4, $5, $6, $8, $9); 
      $this->eat_semis(); 
    }
  | mods_opt T_CLASS ident gen_defs_opt ext_opt impl_opt ';'               
    { 
      $$ = @ClassDecl($1, $3, $4, $5, $6, null, null, true); 
      $this->eat_semis(); 
    }
  ;
  
gen_defs_opt
  : /* empty */      { $$ = null; }
  | '<' gen_defs '>' { $$ = null; }
  ;
  
gen_defs
  : ident                { $$ = null; }
  | ident T_IS type_name { $$ = $null; }
  | gen_defs ',' ident   { $$ = null; }
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
  
trait_uses_opt
  : /* empty */ { $$ = null; }
  | trait_uses  { $$ = $1; }
  ;
  
trait_uses
  : trait_use            { $$ = [ $1 ]; }
  | trait_uses trait_use { $1[] = $2; $$ = $1; }
  ;
  
trait_use
  : T_USE name ';'                       
    { 
      $$ = @TraitUse($2, null); 
      $this->eat_semis(); 
    }
  | T_USE name '{' trait_use_items '}' 
    { 
      $$ = @TraitUse($2, $4); 
      $this->eat_semis();
    }
  ;
  
trait_use_items
  : trait_use_item                 { $$ = [ $1 ]; }
  | trait_use_items trait_use_item { $1[] = $2; $$ = $1; }
  ;
  
trait_use_item
  : ident ';'                     
    { 
      $$ = @TraitItem($1, null, null); 
      $this->eat_semis(); 
    }
  | ident T_AS mods ';'
    {
      $$ = @TraitItem($1, $3, null);
      $this->eat_semis();
    }
  | ident T_AS mods_opt ident ';' 
    { 
      $$ = @TraitItem($1, $3, $4); 
      $this->eat_semis(); 
    }
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
  : fn_decl                                      { $$ = $1; }
  | var_decl                                     { $$ = $1; }
  | enum_decl                                    { $$ = $1; }
  | mods_opt T_NEW '(' ctor_params_opt ')' block { $$ = @CtorDecl($1, $4, $6); }
  | mods_opt T_NEW '(' ctor_params_opt ')' ';'         
    { 
      $$ = @CtorDecl($1, $4, null); 
      $this->eat_semis(); 
    }
  | mods_opt T_DEL pparams block        { $$ = @DtorDecl($1, $3, $4); }
  | mods_opt T_DEL pparams ';'         
    { 
      $$ = @DtorDecl($1, $4, null); 
      $this->eat_semis(); 
    }
  | T_GET ident pparams block           { $$ = @GetterDecl($2, $3, $4); }
  | T_GET ident pparams T_ARR rxpr ';' 
    { 
      $$ = @GetterDecl($2, $3, $5); 
      $this->eat_semis(); 
    }
  | T_SET ident pparams block           { $$ = @SetterDecl($2, $3, $4); }
  | T_SET ident pparams T_ARR rxpr ';' 
    { 
      $$ = @GetterDecl($2, $3, $5); 
      $this->eat_semis(); 
    }
  | mods '{' members_opt '}'            { $$ = @NestedMods($1, $3); }
  ;
  
ctor_params_opt
  : /* empty */ { $$ = []; }
  | ctor_params { $$ = $1; }
  ;
  
ctor_params
  : ctor_param                 { $$ = [ $1 ]; }
  | ctor_params ',' ctor_param { $1[] = $3; $$ = $1; }
  ;

ctor_param
  : param                            { $$ = $1; }
  | T_THIS '.' aid hint_opt          { $$ = @ThisParam($3, $4, null, false); }
  | T_THIS '.' aid hint_opt '=' rxpr { $$ = @ThisParam($3, $4, $6, false); }
  | '&' T_THIS '.' aid hint_opt      { $$ = @ThisParam($4, $5, null, true); }
  ;
  
trait_decl
  : mods_opt T_TRAIT ident '{' trait_uses_opt members_opt '}' 
    { 
      $$ = @TraitDecl($1, $3, $5, $6); 
      $this->eat_semis(); 
    }
  | mods_opt T_TRAIT ident ';'  /* allowed? */
    { 
      $$ = @TraitDecl($1, $3, null, null, true); 
      $this->eat_semis(); 
    }
  ;

iface_decl
  : mods_opt T_IFACE ident gen_defs_opt exts_opt '{' members_opt '}' 
    { 
      $$ = @IfaceDecl($1, $3, $4, $5, $7); 
      $this->eat_semis(); 
    }
  | mods_opt T_IFACE ident gen_defs_opt exts_opt ';' /* allowed? */                
    { 
      $$ = @IfaceDecl($1, $3, $4, $5, null, true); 
      $this->eat_semis(); 
    } 
  ;

vars
  : var          { $$ = [ $1 ]; }
  | vars ',' var { $1[] = $3; $$ = $1; }
  ;
  
var
  : ident hint_opt             { $$ = @VarItem($1, $2, null, false); }
  | ident hint_opt '=' rxpr    { $$ = @VarItem($1, $2, $4, false); }
  | ident hint_opt T_AREF nxpr { $$ = @VarItem($1, $2, $4, true); }
  ;

vars_noin
  : var_noin               { $$ = [ $1 ]; }
  | vars_noin ',' var_noin { $1[] = $3; $$ = $1; }
  ;
  
var_noin
  : ident hint_opt               { $$ = @VarItem($1, $2, null, false); }
  | ident hint_opt '=' rxpr_noin { $$ = @VarItem($1, $2, $4, false); }
  | ident hint_opt T_AREF nxpr   { $$ = @VarItem($1, $2, $4, true); }
  ;

inner
  : comp       { $$ = [ $1 ]; }
  | inner comp { $1[] = $2; $$ = $1; }
  ;

comp
  : fn_decl        { $$ = $1; }
  | var_decl       { $$ = $1; }
  | label_decl     { $$ = $1; }
  | stmt           { $$ = $1; }
  ;

var_list
  : ident              { $$ = [ $1 ]; }
  | var_list ',' ident { $1[] = $3; $$ = $1; }
  ;

var_decl
  : T_LET vars ';' 
    { 
      $$ = @VarDecl(null, $2); 
      $this->eat_semis(); 
    }
  | T_LET '(' var_list ')' '=' rxpr ';'
    {
      $$ = @VarList($3, $6);
      $this->eat_semis();
    }
  | mods vars ';' 
    { 
      $$ = @VarDecl($1, $2); 
      $this->eat_semis(); 
    }
  ;
    
var_decl_noin_nosemi
  : T_LET vars_noin                      { $$ = @VarDecl(null, $2); }
  | T_LET '(' var_list ')' '=' rxpr_noin { $$ = @VarList($3, $6); }
  | mods vars_noin                       { $$ = @VarDecl($1, $2); }
  ;

fn_decl
  : mods_opt T_FN rewrite_opt ident pparams hint_opt fn_decl_body 
    {
      $$ = @FnDecl($1, $4, $5, $6, $7); 
    }
  | mods_opt T_FN rewrite_opt ident pparams hint_opt ';'
    { 
      $$ = @FnDecl($1, $4, $5, $6, null); 
      $this->eat_semis(); 
    }
  | mods_opt T_FN rewrite_opt ident hint_opt ';'
    { 
      $$ = @FnDecl($1, $4, $5, null, null); 
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
  : T_FN ident pparams hint_opt fn_expr_body { $$ = @FnExpr($2, $3, $4, $5); }
  | T_FN       pparams hint_opt fn_expr_body { $$ = @FnExpr(null, $2, $3, $4); }
  ;
  
fn_expr_noin
  : T_FN ident pparams hint_opt fn_expr_body_noin { $$ = @FnExpr($2, $3, $4, $5); }
  | T_FN       pparams hint_opt fn_expr_body_noin { $$ = @FnExpr(null, $2, $3, $4); }
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

/* old syntax */
/*
param
  : ident                            { $$ = @Param(false, null, null, $1, null, false); }
  | ident '?'                        { $$ = @Param(false, null, null, $1, null, true); }
  | ident '=' rxpr                   { $$ = @Param(false, null, null, $1, $3, false); }
  | hint ident                       { $$ = @Param(false, null, $1, $2, null, false); }
  | hint ident '?'                   { $$ = @Param(false, null, $1, $2, null, true); }
  | hint ident '=' rxpr              { $$ = @Param(false, null, $1, $2, $4, false); }
  | mods ident                       { $$ = @Param(false, $1, null, $2, null, false); }
  | mods ident '?'                   { $$ = @Param(false, $1, null, $2, null, true); }
  | mods ident '=' rxpr              { $$ = @Param(false, $1, null, $2, $4, false); }
  | mods hint ident                  { $$ = @Param(false, $1, $2, $3, null, false); }
  | mods hint ident '?'              { $$ = @Param(false, $1, $2, $3, null, true); }
  | mods hint ident '=' rxpr         { $$ = @Param(false, $1, $2, $3, $5, false); }
  | hint_opt T_REST ident            { $$ = @RestParam($1, $3, false); }
  | hint '&' T_REST ident            { $$ = @RestParam($1, $4, true); }
  | '&' T_REST ident                 { $$ = @RestParam(null, $3, true); }
  | '&' ident                        { $$ = @Param(true, null, null, $2, null, false); }
  | '&' ident '?'                    { $$ = @Param(true, null, null, $2, null, true); }
  | hint '&' ident                   { $$ = @Param(true, null, $1, $3, null, false); }
  | hint '&' ident '?'               { $$ = @Param(true, null, $1, $3, null, true); }
  | mods '&' ident                   { $$ = @Param(true, $1, null, $3, null, false); }
  | mods '&' ident '?'               { $$ = @Param(true, $1, null, $3, null, true); }
  | mods hint '&' ident              { $$ = @Param(true, $1, $2, $4, null, false); }
  | mods hint '&' ident '?'          { $$ = @Param(true, $1, $2, $4, null, true); }
  ;
*/

param
  : mods ident hint_opt          { $$ = @Param(false, $1, $2, $3, null, false); }
  | mods ident hint_opt '?'      { $$ = @Param(false, $1, $2, $3, null, true); }
  | mods ident hint_opt '=' rxpr { $$ = @Param(false, $1, $2, $3, $5, false); }
  | mods '&' ident hint_opt      { $$ = @Param(true, $1, $3, $4, null, false); }
  | mods '&' ident hint_opt '?'  { $$ = @Param(true, $1, $3, $4, null, true); }
  | ident hint_opt               { $$ = @Param(false, null, $1, $2, null, false); }
  | ident hint_opt '?'           { $$ = @Param(false, null, $1, $2, null, true); }
  | ident hint_opt '=' rxpr      { $$ = @Param(false, null, $1, $2, $4, false); }
  | '&' ident hint_opt           { $$ = @Param(true, null, $2, $3, null, false); }
  | '&' ident hint_opt '?'       { $$ = @Param(true, null, $2, $3, null, true); }
  | T_REST ident hint_opt        { $$ = @RestParam($2, $3, false); }
  | '&' T_REST ident hint_opt    { $$ = @RestParam($3, $4, true); }  
  ;

hint_opt
  : /* empty */ { $$ = null; }
  | hint        { $$ = $1; }
  ;
  
hint
  : ':' hint_type { $$ = $2; }
  ;
  
hint_types
  : hint_type                { $$ = null; }
  | hint_types ',' hint_type { $$ = null; }
  ;
  
hint_type
  : type_name                  { $$ = $1; }
  | T_FN hint_pparams hint_opt { $$ = null; }
  ;

hint_pparams
  : '(' ')'             { $$ = null; }
  | '(' hint_params ')' { $$ = null; }
  ;
  
hint_params
  : hint_param                 { $$ = null; }
  | hint_params ',' hint_param { $$ = null; }
  ;

hint_param
  : mods hint_type       { $$ = null; }
  | mods hint_type '?'   { $$ = null; }
  | '&' hint_type        { $$ = null; }
  | '&' hint_type '?'    { $$ = null; }
  | T_REST               { $$ = null; }
  | T_REST hint_type     { $$ = null; }
  | '&' T_REST           { $$ = null; }
  | '&' T_REST hint_type { $$ = null; }
  ;

stmt
  : block                                             { $$ = $1; }
  | T_DO stmt T_WHILE pxpr ';'                        { $$ = @DoStmt($2, $4); }
  | T_IF pxpr stmt elifs_opt else_opt                 { $$ = @IfStmt($2, $3, $4, $5); }
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
  
elifs_opt
  : /* empty */ { $$ = null; }
  | elifs       { $$ = $1; }
  ;
  
elifs
  : elif       { $$ = [ $1 ]; }
  | elifs elif { $1[] = $2; $$ = $1; }
  ;
  
elif
  : T_ELIF pxpr stmt { $$ = @ElifItem($2, $3); }
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
  : T_CATCH block                         { $$ = @CatchItem(null, null, $2); }
  | T_CATCH '(' name ')' block            { $$ = @CatchItem($3, null, $5); }
  | T_CATCH '(' name T_AS ident ')' block { $$ = @CatchItem($3, $5, $7); }
  | T_CATCH '(' error ')' block           { $$ = null; }
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
  : '{' '}'       { $$ = @Block([]); }
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
  | lxpr T_POW rxpr         { $$ = @BinExpr($1, $2, $3); }
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
  | lxpr '.' aid            { $$ = @MemberExpr($1, $3); }
  | lxpr '.' '{' rxpr '}'   { $$ = @MemberExpr($1, $4, true); }
  | lxpr '[' rxpr ']'       { $$ = @OffsetExpr($1, $3); }
  | lxpr '[' error ']'      { $$ = null; }
  | lxpr '?' rxpr ':' rxpr  { $$ = @CondExpr($1, $3, $5); } 
  | lxpr '?' ':' rxpr       { $$ = @CondExpr($1, null, $4); }
  | lxpr pargs              { $$ = @CallExpr($1, $2); }
  | T_YIELD rxpr            { $$ = @YieldExpr(null, $2); }
  | T_YIELD rxpr ':' rxpr   { $$ = @YieldExpr($2, $4); }
  | '-' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '+' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '~' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '&' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '!' rxpr                { $$ = @UnaryExpr($1, $2); }
  | T_INC rxpr %prec '!'    { $$ = @UpdateExpr(true, $2, $1); }
  | T_DEC rxpr %prec '!'    { $$ = @UpdateExpr(true, $2, $1); }
  | T_NEW type_id           { $$ = @NewExpr($2, null); }
  | T_NEW type_id pargs     { $$ = @NewExpr($2, $3); }
  | T_NEW nxpr              { $$ = @NewExpr($2, null); }
  | T_NEW nxpr pargs        { $$ = @NewExpr($2, $3); }
  | T_NEW pargs             { $$ = null; }
  | T_DEL nxpr              { $$ = @DelExpr($2); }
  | atom                    { $$ = $1; }
  ;

/* right expression */
/* this kind of expression covers all valid operations */

rxpr
  : rxpr '+' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr '-' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr '*' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr '/' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr '%' rxpr           { $$ = @BinExpr($1, $2, $3); }
  | rxpr T_POW rxpr         { $$ = @BinExpr($1, $2, $3); }
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
  | rxpr '.' aid            { $$ = @MemberExpr($1, $3); }
  | rxpr '.' '{' rxpr '}'   { $$ = @MemberExpr($1, $4, true); }
  | rxpr '[' rxpr ']'       { $$ = @OffsetExpr($1, $3); }
  | rxpr '[' error ']'      { $$ = null; }
  | rxpr '?' rxpr ':' rxpr  { $$ = @CondExpr($1, $3, $5); } 
  | rxpr '?' ':' rxpr       { $$ = @CondExpr($1, null, $4); }
  | rxpr pargs              { $$ = @CallExpr($1, $2); }
  | T_YIELD rxpr            { $$ = @YieldExpr(null, $2); }
  | T_YIELD rxpr ':' rxpr   { $$ = @YieldExpr($2, $4); }
  | '-' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '+' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '~' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '&' rxpr %prec '!'      { $$ = @UnaryExpr($1, $2); }
  | '!' rxpr                { $$ = @UnaryExpr($1, $2); }
  | T_INC rxpr %prec '!'    { $$ = @UpdateExpr(true, $2, $1); }
  | T_DEC rxpr %prec '!'    { $$ = @UpdateExpr(true, $2, $1); }
  | T_NEW type_id           { $$ = @NewExpr($2, null); }
  | T_NEW type_id pargs     { $$ = @NewExpr($2, $3); }
  | T_NEW nxpr              { $$ = @NewExpr($2, null); }
  | T_NEW nxpr pargs        { $$ = @NewExpr($2, $3); }
  | T_NEW pargs             { $$ = null; }
  | T_DEL nxpr              { $$ = @DelExpr($2); }
  | atom                    { $$ = $1; }
  | obj                     { $$ = $1; }
  | fn_expr                 { $$ = $1; }
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
  | rxpr_noin '.' aid                 { $$ = @MemberExpr($1, $3); }
  | rxpr_noin '.' '{' rxpr '}'        { $$ = @MemberExpr($1, $4, true); }
  | rxpr_noin '[' rxpr ']'            { $$ = @OffsetExpr($1, $3); }
  | rxpr_noin '[' error ']'           { $$ = null; }
  | rxpr_noin '?' rxpr ':' rxpr_noin  { $$ = @CondExpr($1, $3, $5); } 
  | rxpr_noin '?' ':' rxpr_noin       { $$ = @CondExpr($1, null, $4); }
  | rxpr_noin pargs                   { $$ = @CallExpr($1, $2); }
  | T_YIELD rxpr_noin                 { $$ = @YieldExpr(null, $2); }
  | T_YIELD rxpr_noin ':' rxpr_noin   { $$ = @YieldExpr($2, $4); }
  | '-' rxpr_noin %prec '!'           { $$ = @UnaryExpr($1, $2); }
  | '+' rxpr_noin %prec '!'           { $$ = @UnaryExpr($1, $2); }
  | '~' rxpr_noin %prec '!'           { $$ = @UnaryExpr($1, $2); }
  | '&' rxpr_noin %prec '!'           { $$ = @UnaryExpr($1, $2); }
  | '!' rxpr_noin                     { $$ = @UnaryExpr($1, $2); }
  | T_INC rxpr_noin %prec '!'         { $$ = @UpdateExpr(true, $2, $1); }
  | T_DEC rxpr_noin %prec '!'         { $$ = @UpdateExpr(true, $2, $1); }
  | T_NEW type_id                     { $$ = @NewExpr($2, null); }
  | T_NEW type_id pargs               { $$ = @NewExpr($2, $3); }
  | T_NEW nxpr                        { $$ = @NewExpr($2, null); }
  | T_NEW nxpr pargs                  { $$ = @NewExpr($2, $3); }
  | T_NEW pargs             { $$ = null; }
  | T_DEL nxpr                        { $$ = @DelExpr($2); }
  | atom                              { $$ = $1; }
  | obj                               { $$ = $1; }
  | fn_expr_noin                      { $$ = $1; }
  ;
 
nxpr
  : nxpr '.' ident        { $$ = @MemberExpr($1, $3); }
  | nxpr '.' '{' rxpr '}' { $$ = @MemberExpr($1, $4, true); }
  | nxpr '[' rxpr ']'     { $$ = @OffsetExpr($1, $3); }
  | nxpr '[' error ']'    { $$ = null; }
  | name                  { $$ = $1; }
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
  : num  { $$ = $1; }
  | reg  { $$ = $1; }
  | arr  { $$ = $1; }
  | name { $$ = $1; }
  | kwc  { $$ = $1; }
  | str  { $$ = $1; }
  | tup  { $$ = $1; }
  ;
 
tup
  : '(' rseq comma_opt ')' 
    { 
      if ($2 === null || (count($2) === 1 && $3 === null))
        $$ = @ParenExpr($2[0]);
      else
        $$ = @TupleExpr($2);
    }
  | '(' ')'       { $$ = @TupleExpr(null); }
  | '(' error ')' { $$ = null; }
  ;
 
reg
  : '/'                { $this->lex->scan_regexp($1); } 
    T_REGEXP %prec '!' { $$ = @RegexpLit($3->value); }
  ;
  
name
  : ident                 { $$ = @Name($1, false); }
  | T_SELF T_DDDOT aid    { $$ = @Name($3, false, $1->type); }
  | T_DDDOT aid           { $$ = @Name($2, true); }
  | name T_DDDOT aid      { $1->add($3); $$ = $1; }
  ;

type_name
  : name          { $$ = $1; }
  | name gen_args { $$ = null; }
  | type_id       { $$ = $1; }
  ;

type_id
  : T_SELF   { $$ = @SelfExpr; }
  | T_TINT   { $$ = @TypeId($1->type); }
  | T_TBOOL  { $$ = @TypeId($1->type); }
  | T_TFLOAT { $$ = @TypeId($1->type); }
  | T_TSTR   { $$ = @TypeId($1->type); }
  | T_TDEC   { $$ = @TypeId($1->type); }
  | T_TANY   { $$ = @TypeId($1->type); }
  ;
    
gen_args
  : '<' hint_types '>'  { $$ = null; }
  | '<' hint_types T_SR
    { 
      $tok = new Token(T_GT, '>');
      $tok->loc = $3->loc;
      $tok->loc->pos->coln += 1;
      $this->lex->push($tok);
      $$ = null;
    }
  ;

ident
  : T_IDENT   { $$ = @Ident($1->value); }
  | T_GET     { $$ = @Ident($1->value); }
  | T_SET     { $$ = @Ident($1->value); }
  ;
 
kwc
  : T_THIS    { $$ = @ThisExpr; }
  | T_SUPER   { $$ = @SuperExpr; }
  | T_SELF    { $$ = @SelfExpr; }
  | T_NULL    { $$ = @NullLit; }
  | T_TRUE    { $$ = @TrueLit; }
  | T_FALSE   { $$ = @FalseLit; }
  | T_CDIR    { $$ = @KStrLit($this->cdir); }
  | T_CFILE   { $$ = @KStrLit($this->cfile); }
  | T_CLINE   { $$ = @LNumLit($1->loc->pos->line); }
  | T_CCOLN   { $$ = @LNumLit($1->loc->pos->coln); }
  | T_CFN     { $$ = @EngineConst($1->type); }
  | T_CCLASS  { $$ = @EngineConst($1->type); }
  | T_CTRAIT  { $$ = @EngineConst($1->type); }
  | T_CMETHOD { $$ = @EngineConst($1->type); }
  | T_CMODULE { $$ = @EngineConst($1->type); }
  ;
  
str
  : T_STRING                 { $$ = @StrLit($1); }
  | str T_SUBST '{' rxpr '}' { $1->add($4); $$ = $1; }
  | str T_SUBST T_STRING     { $1->add(@StrLit($3)); $$ = $1; }
  ;
  
num
  : T_LNUM { $$ = @LNumLit($1->value); }
  | T_DNUM { $$ = @DNumLit($1->value); }
  | T_SNUM { $$ = @SNumLit($1->value, $1->suffix); }
  ;

arr
  : '[' ']'                                    { $$ = @ArrLit(null); }
  | '[' rxpr T_FOR '(' ident T_IN rxpr ')' ']' { $$ = @ArrGen($2, $5, $7); }
  | '[' arr_vals ']'                           { $$ = @ArrLit($2); }
  | '[' error ']'                              { $$ = null; }
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
  : aid          { $$ = $1; }
  | str          { $$ = $1; }
  | '(' rxpr ')' { $$ = @ObjKey($2); }    
  ;
  
comma_opt
  : /* empty */ { $$ = null; }
  | ','         { $$ = $1; }
  ;
  
aid
  : ident { $$ = $1; }
  | rid   { $$ = @Ident($1->value); }
  ;
  
rid
  : T_FN        { $$ = $1; }
  | T_LET       { $$ = $1; }
  | T_USE       { $$ = $1; }
  | T_ENUM      { $$ = $1; }
  | T_CLASS     { $$ = $1; }
  | T_TRAIT     { $$ = $1; }
  | T_IFACE     { $$ = $1; }
  | T_MODULE    { $$ = $1; }
  | T_REQUIRE   { $$ = $1; }
  | T_TRUE      { $$ = $1; }
  | T_FALSE     { $$ = $1; }
  | T_NULL      { $$ = $1; }
  | T_THIS      { $$ = $1; }
  | T_SUPER     { $$ = $1; }
  | T_SELF      { $$ = $1; }
  | T_DO        { $$ = $1; }
  | T_IF        { $$ = $1; }
  | T_ELIF      { $$ = $1; }
  | T_ELSE      { $$ = $1; }
  | T_FOR       { $$ = $1; }
  | T_TRY       { $$ = $1; }
  | T_GOTO      { $$ = $1; }
  | T_BREAK     { $$ = $1; }
  | T_CONTINUE  { $$ = $1; }
  | T_THROW     { $$ = $1; }
  | T_CATCH     { $$ = $1; }
  | T_FINALLY   { $$ = $1; }
  | T_WHILE     { $$ = $1; }
  | T_ASSERT    { $$ = $1; }
  | T_SWITCH    { $$ = $1; }
  | T_CASE      { $$ = $1; }
  | T_DEFAULT   { $$ = $1; }
  | T_RETURN    { $$ = $1; }
  | T_PRINT     { $$ = $1; }
  | T_CONST     { $$ = $1; }
  | T_FINAL     { $$ = $1; }
  | T_STATIC    { $$ = $1; }
  | T_EXTERN    { $$ = $1; }
  | T_PUBLIC    { $$ = $1; }
  | T_PRIVATE   { $$ = $1; }
  | T_PROTECTED { $$ = $1; }
  | T_SEALED    { $$ = $1; }
  | T_INLINE    { $$ = $1; }
  | T_GLOBAL    { $$ = $1; }
  | T_PHP       { $$ = $1; }
  | T_TEST      { $$ = $1; }
  | T_YIELD     { $$ = $1; }
  | T_NEW       { $$ = $1; }
  | T_DEL       { $$ = $1; }
  | T_AS        { $$ = $1; }
  | T_IS        { $$ = $1; }
  | T_IN        { $$ = $1; }
  | T_TINT      { $$ = $1; }
  | T_TBOOL     { $$ = $1; }
  | T_TFLOAT    { $$ = $1; }
  | T_TSTR      { $$ = $1; }
  | T_TTUP      { $$ = $1; }
  | T_TDEC      { $$ = $1; }
  | T_CDIR      { $$ = $1; }
  | T_CFILE     { $$ = $1; }
  | T_CLINE     { $$ = $1; }
  | T_CCOLN     { $$ = $1; }
  | T_CFN       { $$ = $1; }
  | T_CCLASS    { $$ = $1; }
  | T_CTRAIT    { $$ = $1; }
  | T_CMETHOD   { $$ = $1; }
  | T_CMODULE   { $$ = $1; }
  ;
  
%%
