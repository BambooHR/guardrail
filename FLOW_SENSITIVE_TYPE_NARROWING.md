# Flow-Sensitive Type Narrowing Implementation

## Overview

This implementation adds comprehensive flow-sensitive type analysis to Guardrail, enabling precise null-safety detection and undefined variable tracking across all control structures.

## Core Features

### 1. Enhanced Variable Tracking

**ScopeVar Enhancements** (`src/Scope/ScopeVar.php`):
- `mayBeNull`: Tracks if a variable may be null (can be narrowed to false)
- `mayBeUnset`: Tracks if a variable may be unset (created in some branches but not others)

### 2. Type Assertion System

**TypeAssertion Class** (`src/TypeInference/TypeAssertion.php`):

Handles condition-based type narrowing:

- `instanceof Foo` → Variable is `Foo`, `mayBeNull = false`, `mayBeUnset = false`
- `if ($var)` → `mayBeNull = false` in truthy branch
- `$var === null` → `mayBeNull = true` in truthy, `false` in falsy
- `$var !== null` → `mayBeNull = false` in truthy, `true` in falsy
- `is_null($var)` → `mayBeNull = true` in truthy, `false` in falsy
- `isset($var)` → `mayBeNull = false`, `mayBeUnset = false` in truthy
- `is_string()`, `is_int()`, etc. → Type narrowing + `mayBeNull = false`
- `!$condition` → Inverts the narrowing
- `$a && $b` → Both narrowings apply in truthy branch
- `$a || $b` → Complex union handling

### 3. Branch Merging

**Scope Enhancements** (`src/Scope/Scope.php`):

- `mergeBranches()`: Merges multiple branch scopes
  - Tracks which branches exited early (return/throw/break)
  - Only merges branches that complete normally
  - Sets `mayBeUnset = true` for variables not in all branches
  - Unions types from all branches
  - Handles implicit branches (if without else, switch without default)

- `unionTypes()`: Creates union types from multiple type nodes
  - Flattens nested unions
  - Removes duplicates
  - Handles null types

## Control Structure Support

### If/Else/ElseIf (`src/Evaluators/If_.php`)

**Features:**
- Applies type narrowing when entering then/elseif branches
- Tracks all branch scopes and which ones exit early
- Merges branches properly at the end
- Special handling for early-exit-only if statements (applies inverse narrowing)

**Example:**
```php
if ($x !== null) {
    $x->method(); // OK: narrowed to non-null
}

if ($x === null) {
    return;
}
$x->method(); // OK: inverse narrowing from early exit
```

### Switch/Case (`src/Evaluators/Switch_.php`)

**Features:**
- Creates scopes for each case
- Handles fall-through correctly (continues same scope)
- Tracks which cases exit/break vs fall through
- Merges all case scopes at end of switch
- Handles missing default case (sets `mayBeUnset` appropriately)

**Example:**
```php
switch ($value) {
    case 1:
        $x = "one";
        break;
    case 2:
        $x = "two";
        break;
    default:
        $x = "other";
        break;
}
echo $x; // OK: set in all cases including default
```

### While/Do-While Loops (`src/Evaluators/Loop.php`)

**Features:**
- **While loops**: Applies truthy narrowing inside loop, inverse narrowing after loop
- **Do-while loops**: Merges loop scope, then applies inverse narrowing
- After `while ($row = fetch())`, `$row` is known to be null/false

**Example:**
```php
while ($row = $stmt->fetch()) {
    $row->process(); // OK: truthy inside loop
}
$row->process(); // ERROR: null after loop (inverse narrowing)
```

### Try/Catch/Finally (`src/Evaluators/TryCatch.php`)

**Features:**
- Variables declared in `try{}` are marked `mayBeUnset = true` (any line could throw)
- Variables declared in `catch{}` are marked `mayBeUnset = true` (only set if exception)
- Variables declared in `finally{}` are guaranteed set (`mayBeUnset = false`)
- Exception variables in catch blocks are guaranteed non-null

**Example:**
```php
try {
    $x = getValue(); // May throw before this
} catch (Exception $e) {
    $y = "error";    // Only set if exception
} finally {
    $z = "always";   // Always set
}

echo $x; // ERROR: mayBeUnset
echo $y; // ERROR: mayBeUnset
echo $z; // OK: finally always runs
```

### Short-Circuit Evaluation (`src/Evaluators/Expression.php`)

**Features:**
- **AND (`&&`)**: Right side has left narrowed to truthy
- **OR (`||`)**: Right side has left narrowed to falsy
- **Ternary**: Applies narrowing to then-branch
- Properly handles assignments in conditions

**Example:**
```php
if ($x !== null && $x->isValid()) {
    $x->process(); // OK: both conditions true, $x non-null
}

if (($x = getValue()) && $x->isValid()) {
    $x->process(); // OK: $x assigned and truthy
}
```

## Test Suite

**Comprehensive Test File** (`tests/FlowSensitiveTypeNarrowingTest.php`):

Covers 11 categories of scenarios:
1. Basic If Statement Narrowing
2. If/Else Branch Merging
3. Switch Statement Tests
4. Loop Tests
5. Try/Catch/Finally Tests
6. Short-Circuit Evaluation Tests
7. Ternary Operator Tests
8. Type Check Function Tests
9. Negation Tests
10. Complex Nested Scenarios
11. Edge Cases

Total: 60+ test methods covering all combinations of:
- Null narrowing
- Undefined variable detection
- Branch merging
- Early exits
- Fall-through
- Inverse assertions
- Nested control structures

## Usage

The system automatically tracks variable states through all control flow. Errors will be reported when:

1. **Null dereference**: Calling methods/accessing properties on variables with `mayBeNull = true`
2. **Undefined variable**: Using variables with `mayBeUnset = true`

## Implementation Details

### Type Narrowing Flow

1. **On entering control structure**: Create scope clone, apply type narrowing
2. **Inside branch**: Variables have narrowed types and flags
3. **On exiting control structure**: Merge all branch scopes
4. **After merge**: Variables have union of all branch types and combined flags

### Flag Merging Rules

- `mayBeNull`: `true` if ANY branch has it `true`
- `mayBeUnset`: `true` if variable doesn't exist in ALL branches OR any branch has it `true`

### Early Exit Handling

Branches that exit early (return/throw/break/continue) are excluded from merging:
- Their variable states don't pollute the merged result
- Inverse narrowing is applied for single-branch early exits

### Special Cases

1. **Empty if blocks**: Branches still merge (no narrowing persists)
2. **All branches exit**: No merging needed (code after is unreachable)
3. **Fall-through in switch**: Scope continues to next case
4. **Finally blocks**: Always execute, variables guaranteed set
5. **Loop conditions**: Inverse narrowing applied after loop exits

## Files Modified/Created

### Created:
- `src/TypeInference/TypeAssertion.php` - Type narrowing logic
- `src/Evaluators/Switch_.php` - Switch statement handling
- `src/Evaluators/Loop.php` - While/do-while handling
- `src/Evaluators/TryCatch.php` - Try/catch/finally handling
- `tests/FlowSensitiveTypeNarrowingTest.php` - Comprehensive test suite
- `FLOW_SENSITIVE_TYPE_NARROWING.md` - This documentation

### Modified:
- `src/Scope/ScopeVar.php` - Added `mayBeNull` and `mayBeUnset` flags
- `src/Scope/Scope.php` - Added `mergeBranches()` and `unionTypes()` methods
- `src/Evaluators/If_.php` - Enhanced with type narrowing and proper branch merging
- `src/Evaluators/Expression.php` - Enhanced short-circuit evaluation with type narrowing

## Benefits

1. **Precise null-safety**: Knows when variables are guaranteed non-null
2. **Undefined detection**: Tracks variables that may not be set
3. **Fewer false positives**: Understands control flow and type narrowing
4. **Better error messages**: Can distinguish between null and undefined
5. **Comprehensive coverage**: All control structures supported

## Future Enhancements

Potential improvements:
- Array key existence tracking
- Property existence tracking
- More sophisticated type narrowing (e.g., `is_a()`, `method_exists()`)
- Inter-procedural analysis
- Path-sensitive analysis for complex conditions
