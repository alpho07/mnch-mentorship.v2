import { createRoot } from "react-dom/client";
import App from "./App.jsx";

// StrictMode intentionally omitted — it double-invokes effects in dev,
// causing duplicate /auth/me calls in the network tab.
createRoot(document.getElementById("root")).render(<App />);
