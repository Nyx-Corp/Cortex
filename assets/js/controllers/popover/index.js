import PopoverController from "./controller.js";

export function registerPopoverController(application) {
    application.register("popover", PopoverController);
}

export { PopoverController };
