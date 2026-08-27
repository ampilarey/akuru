"""Multi-output CNN for isolated Arabic letter + haraka (SPEC 51.11).

Shared convolutional feature extractor with two softmax heads:
output 1 = letter, output 2 = haraka.
"""

from __future__ import annotations


def build_model(input_shape, letter_count: int, haraka_count: int):
    from tensorflow import keras

    inputs = keras.Input(shape=input_shape)
    x = keras.layers.Conv2D(32, 3, activation="relu", padding="same")(inputs)
    x = keras.layers.MaxPooling2D()(x)
    x = keras.layers.Conv2D(64, 3, activation="relu", padding="same")(x)
    x = keras.layers.MaxPooling2D()(x)
    x = keras.layers.Conv2D(128, 3, activation="relu", padding="same")(x)
    x = keras.layers.GlobalAveragePooling2D()(x)
    x = keras.layers.Dropout(0.3)(x)
    shared = keras.layers.Dense(128, activation="relu")(x)

    letter_out = keras.layers.Dense(letter_count, activation="softmax", name="letter")(shared)
    haraka_out = keras.layers.Dense(haraka_count, activation="softmax", name="haraka")(shared)

    model = keras.Model(inputs, [letter_out, haraka_out])
    model.compile(
        optimizer="adam",
        loss={"letter": "sparse_categorical_crossentropy", "haraka": "sparse_categorical_crossentropy"},
        metrics={"letter": "accuracy", "haraka": "accuracy"},
    )
    return model
