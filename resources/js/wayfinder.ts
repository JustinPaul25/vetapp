// Type definitions for route handling
export interface RouteQueryOptions {
    [key: string]: string | number | boolean | null | undefined;
}

export interface RouteDefinition<T extends string = string> {
    url: string;
    method: T;
}

export interface RouteFormDefinition<T extends string = string> extends RouteDefinition<T> {
    // Additional form-specific properties can be added here
}

/**
 * Converts query options to URL query string
 * @param options - Object containing query parameters
 * @returns Query string (empty string if no options or "?key=value&key2=value2")
 */
export function queryParams(options?: RouteQueryOptions): string {
    if (!options || Object.keys(options).length === 0) {
        return '';
    }

    const params = new URLSearchParams();
    
    for (const [key, value] of Object.entries(options)) {
        if (value !== null && value !== undefined) {
            params.append(key, String(value));
        }
    }

    const queryString = params.toString();
    return queryString ? `?${queryString}` : '';
}

/**
 * Applies default values to URL parameters
 * Ensures that arguments are properly formatted for URL replacement
 * @param args - The arguments object to process
 * @returns The processed arguments object
 */
export function applyUrlDefaults<T extends Record<string, any>>(args: T): T {
    // If args is already properly formatted, return as-is
    // This function acts as a pass-through but can be extended
    // for more complex URL parameter processing if needed
    return args;
}

