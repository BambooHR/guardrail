<?php

// This file contains functions inside namespaces (should be allowed)
namespace FirstNamespace {
    function duplicate_function_name() {
        return "Function in first namespace";
    }
}

namespace SecondNamespace {
    function duplicate_function_name() {
        return "Function in second namespace";
    }
}
